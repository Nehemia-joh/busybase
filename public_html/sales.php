<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/currency_functions.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Sales';
$action    = clean($_GET['action'] ?? 'list');
$u         = currentUser();

$branches = $db->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll();
if ($u['branch_id'] && !isSuperAdmin() && !hasRole('zone_manager')) {
    $branches = array_filter($branches, fn($b) => (int)$b['id'] === (int)$u['branch_id']);
    $branches = array_values($branches);
}

// ── POST: Process Sale ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = clean($_POST['_act'] ?? '');

    if ($act === 'create_sale') {
        $branchId      = cleanInt($_POST['branch_id'] ?? 0);
        $customerId    = cleanInt($_POST['customer_id'] ?? 0) ?: null;
        $customerName  = clean($_POST['customer_name'] ?? 'Walk-in Customer');
        $customerType  = in_array($_POST['customer_type'] ?? '', ['retail','wholesale']) ? $_POST['customer_type'] : 'retail';
        $payMethod     = in_array($_POST['payment_method'] ?? '', ['cash','card','mobile_money','credit']) ? $_POST['payment_method'] : 'cash';
        $notes         = clean($_POST['notes'] ?? '');
        $discount      = cleanFloat($_POST['discount'] ?? 0);
        $items         = $_POST['items'] ?? [];

        if (!$branchId || empty($items)) {
            flash('error', 'Please select a branch and add at least one item.');
            header('Location: /sales?action=new'); exit;
        }

        $subtotal = 0;
        $saleItems = [];

        foreach ($items as $item) {
            $productId = cleanInt($item['product_id'] ?? 0);
            $qty       = cleanInt($item['quantity'] ?? 0);
            $unitPrice = cleanFloat($item['unit_price'] ?? 0);
            $priceType = in_array($item['price_type'] ?? '', ['retail','wholesale']) ? $item['price_type'] : 'retail';

            if (!$productId || $qty <= 0 || $unitPrice < 0) continue;

            // Check stock
            $stockStmt = $db->prepare('SELECT quantity FROM stock WHERE product_id=? AND branch_id=?');
            $stockStmt->execute([$productId, $branchId]);
            $stockRow = $stockStmt->fetch();
            if (!$stockRow || (int)$stockRow['quantity'] < $qty) {
                flash('error', "Insufficient stock for one or more products.");
                header('Location: /sales?action=new'); exit;
            }

            $prodStmt = $db->prepare('SELECT name, sku FROM products WHERE id=?');
            $prodStmt->execute([$productId]);
            $prod = $prodStmt->fetch();

            $lineTotal = $qty * $unitPrice;
            $subtotal += $lineTotal;
            $saleItems[] = [
                'product_id'  => $productId,
                'product_name'=> $prod['name'],
                'sku'         => $prod['sku'],
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
                'price_type'  => $priceType,
                'total_price' => $lineTotal,
            ];
        }

        if (empty($saleItems)) { flash('error','No valid items in sale.'); header('Location: /sales?action=new'); exit; }

        $total      = $subtotal - $discount;
        $invoiceNo  = generateInvoiceNo();

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO sales (invoice_no,branch_id,customer_id,customer_name,customer_type,subtotal,discount,total,payment_method,amount_paid,notes,cashier_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$invoiceNo,$branchId,$customerId,$customerName,$customerType,$subtotal,$discount,$total,$payMethod,$total,$notes,$u['id']]);
            $saleId = (int)$db->lastInsertId();

            foreach ($saleItems as $si) {
                $db->prepare(
                    'INSERT INTO sale_items (sale_id,product_id,product_name,sku,quantity,unit_price,price_type,total_price) VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$saleId,$si['product_id'],$si['product_name'],$si['sku'],$si['quantity'],$si['unit_price'],$si['price_type'],$si['total_price']]);

                // Deduct stock
                $db->prepare('UPDATE stock SET quantity = quantity - ? WHERE product_id=? AND branch_id=?')
                    ->execute([$si['quantity'],$si['product_id'],$branchId]);

                // Log stock adjustment
                $newQty = (function() use ($db,$si,$branchId) {
                    $s=$db->prepare('SELECT quantity FROM stock WHERE product_id=? AND branch_id=?');
                    $s->execute([$si['product_id'],$branchId]);
                    return (int)$s->fetchColumn();
                })();
                $db->prepare('INSERT INTO stock_adjustments (product_id,branch_id,adjustment_type,quantity_before,quantity_changed,quantity_after,reason,reference,user_id) VALUES (?,?,\'subtract\',?,?,?,?,?,?)')
                    ->execute([$si['product_id'],$branchId,$newQty+$si['quantity'],$si['quantity'],$newQty,'Sale','INV:'.$invoiceNo,$u['id']]);
            }

            // Update customer totals
            if ($customerId) {
                $db->prepare('UPDATE customers SET total_purchases=total_purchases+?, last_purchase_date=CURDATE() WHERE id=?')
                    ->execute([$total,$customerId]);
            }

            $db->commit();
            logActivity('sale_created', "Invoice $invoiceNo for " . formatCurrency($total));
            flash('success', "Sale $invoiceNo created! Total: " . formatCurrency($total));
            header("Location: /sales.php?view=$saleId"); exit;
        } catch (PDOException $e) {
            $db->rollBack();
            flash('error', 'Error processing sale: ' . $e->getMessage());
            header('Location: /sales?action=new'); exit;
        }
    }

    // Quick add customer
    if ($act === 'add_customer') {
        $name  = clean($_POST['cust_name'] ?? '');
        $phone = clean($_POST['cust_phone'] ?? '');
        $email = filter_var(clean($_POST['cust_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        $type  = in_array($_POST['cust_type'] ?? '', ['retail','wholesale','regular']) ? $_POST['cust_type'] : 'retail';
        if ($name) {
            $db->prepare('INSERT INTO customers (name,phone,email,type,created_by) VALUES (?,?,?,?,?)')->execute([$name,$phone,$email,$type,$u['id']]);
            flash('success', "Customer '$name' added.");
        }
        header('Location: /sales?action=new'); exit;
    }
}

// ── View sale ─────────────────────────────────────────────────────────────────
$viewSale = null;
if (isset($_GET['view'])) {
    $sid = cleanInt($_GET['view']);
    $stmt = $db->prepare('SELECT s.*, b.name as branch_name FROM sales s JOIN branches b ON b.id=s.branch_id WHERE s.id=?');
    $stmt->execute([$sid]); $viewSale = $stmt->fetch();
    if ($viewSale) {
        $stmt2 = $db->prepare('SELECT * FROM sale_items WHERE sale_id=?'); $stmt2->execute([$sid]);
        $viewSale['items'] = $stmt2->fetchAll();
    }
}

// ── List ──────────────────────────────────────────────────────────────────────
$search = clean($_GET['q'] ?? '');
$filterBranch = cleanInt($_GET['branch_id'] ?? 0) ?: ($u['branch_id'] ?? 0);

$listWhere = ['1=1'];
$listParams = [];
if ($search) { $listWhere[] = '(s.invoice_no LIKE ? OR s.customer_name LIKE ?)'; $listParams = ["%$search%","%$search%"]; }
if ($filterBranch && !isSuperAdmin()) { $listWhere[] = 's.branch_id = ?'; $listParams[] = $filterBranch; }
elseif ($filterBranch) { $listWhere[] = 's.branch_id = ?'; $listParams[] = $filterBranch; }
$listWhereStr = implode(' AND ', $listWhere);

$salesList = $db->prepare(
    "SELECT s.id, s.invoice_no, s.customer_name, s.customer_type, s.total, s.payment_method,
            s.payment_status, s.created_at, b.name as branch_name, u.full_name as cashier_name
     FROM sales s
     JOIN branches b ON b.id=s.branch_id
     LEFT JOIN users u ON u.id=s.cashier_id
     WHERE $listWhereStr
     ORDER BY s.created_at DESC LIMIT 100"
);
$salesList->execute($listParams);
$sales = $salesList->fetchAll();

// For new sale form
$customers  = $db->query('SELECT id, name, phone, type FROM customers ORDER BY name')->fetchAll();
$allProducts = $filterBranch
    ? $db->prepare('SELECT p.id, p.name, p.sku, p.retail_price, p.wholesale_price, COALESCE(s.quantity,0) as qty FROM products p LEFT JOIN stock s ON s.product_id=p.id AND s.branch_id=? WHERE p.is_active=1 ORDER BY p.name')
    : null;
if ($allProducts && $filterBranch) { $allProducts->execute([$filterBranch]); $allProducts = $allProducts->fetchAll(); }

include __DIR__ . '/includes/tailwind.php';
?>

<?php if ($viewSale): ?>
<!-- ── Sale Receipt ── -->
<div class="max-w-2xl mx-auto">
  <div class="flex items-center gap-3 mb-6">
    <a href="/sales.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h2 class="text-lg font-semibold text-gray-800">Invoice <?= e($viewSale['invoice_no']) ?></h2>
    <button onclick="window.print()" class="ml-auto btn-secondary"><i class="fas fa-print"></i> Print</button>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" id="receipt">
    <div class="flex justify-between items-start mb-6">
      <div>
        <h2 class="text-xl font-bold text-indigo-700">OneSystem BMS</h2>
        <p class="text-sm text-gray-500"><?= e($viewSale['branch_name']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-2xl font-bold text-gray-800"><?= e($viewSale['invoice_no']) ?></p>
        <p class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($viewSale['created_at'])) ?></p>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 rounded-lg text-sm">
      <div><span class="text-gray-500">Customer:</span> <span class="font-medium"><?= e($viewSale['customer_name']) ?></span></div>
      <div><span class="text-gray-500">Type:</span> <span class="font-medium capitalize"><?= e($viewSale['customer_type']) ?></span></div>
      <div><span class="text-gray-500">Payment:</span> <span class="font-medium capitalize"><?= e(str_replace('_',' ',$viewSale['payment_method'])) ?></span></div>
      <div><span class="text-gray-500">Status:</span> <span class="badge <?= $viewSale['payment_status']==='paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?> capitalize"><?= e($viewSale['payment_status']) ?></span></div>
    </div>
    <table class="w-full text-sm mb-4">
      <thead><tr class="border-b-2 border-gray-200">
        <th class="py-2 text-left text-gray-600">Product</th>
        <th class="py-2 text-right text-gray-600">Qty</th>
        <th class="py-2 text-right text-gray-600">Price</th>
        <th class="py-2 text-right text-gray-600">Total</th>
      </tr></thead>
      <tbody>
      <?php foreach ($viewSale['items'] as $si): ?>
      <tr class="border-b border-gray-100">
        <td class="py-2">
          <p class="font-medium"><?= e($si['product_name']) ?></p>
          <p class="text-xs text-gray-400"><?= e($si['sku']) ?> &bull; <?= e($si['price_type']) ?></p>
        </td>
        <td class="py-2 text-right"><?= $si['quantity'] ?></td>
        <td class="py-2 text-right"><?= formatCurrency((float)$si['unit_price']) ?></td>
        <td class="py-2 text-right font-medium"><?= formatCurrency((float)$si['total_price']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="border-t-2 border-gray-800 pt-4 space-y-1 text-sm">
      <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span><?= formatCurrency((float)$viewSale['subtotal']) ?></span></div>
      <?php if ($viewSale['discount'] > 0): ?>
      <div class="flex justify-between text-red-600"><span>Discount</span><span>- <?= formatCurrency((float)$viewSale['discount']) ?></span></div>
      <?php endif; ?>
      <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200">
        <span>TOTAL</span><span><?= formatCurrency((float)$viewSale['total']) ?></span>
      </div>
    </div>
    <?php if ($viewSale['notes']): ?><p class="mt-4 text-xs text-gray-500 italic">Notes: <?= e($viewSale['notes']) ?></p><?php endif; ?>
    <p class="text-center text-xs text-gray-400 mt-6">Thank you for your business!</p>
  </div>
</div>

<?php elseif ($action === 'new'): ?>
<!-- ── New Sale Form ── -->
<div x-data="saleForm()" class="max-w-4xl mx-auto">
  <div class="flex items-center gap-3 mb-6">
    <a href="/sales.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h2 class="text-lg font-semibold text-gray-800">New Sale</h2>
  </div>

  <form method="POST" @submit.prevent="submitSale">
    <?= csrf_field() ?>
    <input type="hidden" name="_act" value="create_sale">

    <div class="grid lg:grid-cols-3 gap-5">

      <!-- Left: Items -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Branch + Customer -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="form-label">Branch <span class="text-red-500">*</span></label>
              <select name="branch_id" x-model="branchId" @change="loadProducts()" required class="form-input">
                <option value="">-- Select Branch --</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Customer Type</label>
              <select name="customer_type" x-model="customerType" class="form-input">
                <option value="retail">Retail</option>
                <option value="wholesale">Wholesale</option>
              </select>
            </div>
            <div>
              <label class="form-label">Customer</label>
              <div class="flex gap-2">
                <select name="customer_id" x-model="customerId" @change="setCustomerName()" class="form-input flex-1">
                  <option value="">Walk-in Customer</option>
                  <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' (' . e($c['phone']) . ')' : '' ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" @click="showAddCustomer=true" class="btn-secondary px-3" title="Add new customer"><i class="fas fa-user-plus"></i></button>
              </div>
            </div>
            <div>
              <label class="form-label">Customer Name</label>
              <input type="text" name="customer_name" x-model="customerName" class="form-input" placeholder="Walk-in Customer">
            </div>
          </div>
        </div>

        <!-- Product selector -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Items</h3>
          <div class="flex gap-2 mb-4">
            <select x-model="selectedProduct" class="form-input flex-1" :disabled="!branchId">
              <option value="">-- Select Product (choose branch first) --</option>
              <template x-for="p in products" :key="p.id">
                <option :value="p.id" :data-retail="p.retail_price" :data-wholesale="p.wholesale_price" :data-qty="p.qty">
                  <span x-text="p.name + ' (' + p.sku + ') — Stock: ' + p.qty"></span>
                </option>
              </template>
            </select>
            <button type="button" @click="addItem()" class="btn-primary px-4">
              <i class="fas fa-plus"></i> Add
            </button>
          </div>

          <!-- Items table -->
          <div x-show="items.length > 0">
            <table class="w-full text-sm">
              <thead><tr class="border-b border-gray-100 text-xs text-gray-500">
                <th class="py-2 text-left">Product</th>
                <th class="py-2 text-center w-20">Qty</th>
                <th class="py-2 text-center w-28">Price Type</th>
                <th class="py-2 text-right w-28">Unit Price</th>
                <th class="py-2 text-right w-28">Total</th>
                <th class="py-2 w-8"></th>
              </tr></thead>
              <tbody>
                <template x-for="(item, idx) in items" :key="idx">
                  <tr class="border-b border-gray-50">
                    <td class="py-2">
                      <p class="font-medium" x-text="item.name"></p>
                      <p class="text-xs text-gray-400" x-text="item.sku"></p>
                      <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.id">
                      <input type="hidden" :name="'items['+idx+'][price_type]'" :value="item.priceType">
                    </td>
                    <td class="py-2">
                      <input type="number" :name="'items['+idx+'][quantity]'" x-model.number="item.qty"
                        @input="calcLine(idx)" min="1" :max="item.stock"
                        class="w-16 border border-gray-200 rounded px-2 py-1 text-center text-sm focus:ring-1 focus:ring-indigo-400">
                    </td>
                    <td class="py-2 text-center">
                      <select :name="'items['+idx+'][price_type]'" x-model="item.priceType" @change="updatePrice(idx)" class="border border-gray-200 rounded px-2 py-1 text-xs">
                        <option value="retail">Retail</option>
                        <option value="wholesale">Wholesale</option>
                      </select>
                    </td>
                    <td class="py-2 text-right">
                      <input type="number" :name="'items['+idx+'][unit_price]'" x-model.number="item.unitPrice"
                        @input="calcLine(idx)" min="0" step="0.01"
                        class="w-24 border border-gray-200 rounded px-2 py-1 text-right text-sm focus:ring-1 focus:ring-indigo-400">
                    </td>
                    <td class="py-2 text-right font-medium" x-text="formatMoney(item.total)"></td>
                    <td class="py-2 text-right">
                      <button type="button" @click="items.splice(idx,1)" class="text-red-400 hover:text-red-600">
                        <i class="fas fa-times text-xs"></i>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div x-show="items.length === 0" class="py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-shopping-cart text-2xl mb-2 block"></i>No items added yet
          </div>
        </div>
      </div>

      <!-- Right: Summary -->
      <div class="space-y-4">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 sticky top-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">Order Summary</h3>
          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between text-gray-600"><span>Items</span><span x-text="items.length"></span></div>
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span x-text="formatMoney(subtotal)"></span></div>
            <div class="flex items-center justify-between text-gray-600">
              <span>Discount</span>
              <input type="number" name="discount" x-model.number="discount" @input="calcTotals()" min="0" step="0.01"
                class="w-24 border border-gray-200 rounded px-2 py-1 text-right text-sm">
            </div>
            <div class="flex justify-between font-bold text-lg border-t pt-2">
              <span>Total</span><span x-text="formatMoney(total)" class="text-indigo-600"></span>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-input">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="credit">Credit</option>
              </select>
            </div>
            <div>
              <label class="form-label">Notes</label>
              <textarea name="notes" rows="2" class="form-input" placeholder="Optional notes"></textarea>
            </div>
            <button type="submit" class="w-full btn-primary justify-center py-3 text-base" :disabled="items.length === 0">
              <i class="fas fa-check-circle"></i> Complete Sale
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Add Customer Modal -->
  <div x-show="showAddCustomer" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.away="showAddCustomer=false">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Add Customer</h3>
        <button @click="showAddCustomer=false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
      </div>
      <form method="POST" class="p-5 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="_act" value="add_customer">
        <div><label class="form-label">Name <span class="text-red-500">*</span></label><input type="text" name="cust_name" required class="form-input"></div>
        <div><label class="form-label">Phone</label><input type="text" name="cust_phone" class="form-input"></div>
        <div><label class="form-label">Email</label><input type="email" name="cust_email" class="form-input"></div>
        <div>
          <label class="form-label">Type</label>
          <select name="cust_type" class="form-input">
            <option value="retail">Retail</option>
            <option value="wholesale">Wholesale</option>
            <option value="regular">Regular</option>
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="submit" class="btn-primary flex-1"><i class="fas fa-save"></i> Save Customer</button>
          <button type="button" @click="showAddCustomer=false" class="btn-secondary">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function saleForm() {
  return {
    branchId: '<?= $u['branch_id'] ?? '' ?>',
    customerId: '',
    customerName: 'Walk-in Customer',
    customerType: 'retail',
    products: [],
    selectedProduct: '',
    items: [],
    discount: 0,
    subtotal: 0,
    total: 0,
    showAddCustomer: false,

    loadProducts() {
      if (!this.branchId) { this.products = []; return; }
      fetch('/get_branch_products.php?branch_id=' + this.branchId + '&_csrf=' + window.CSRF_TOKEN)
        .then(r => r.json())
        .then(data => { this.products = data; })
        .catch(() => { this.products = []; });
    },

    addItem() {
      if (!this.selectedProduct) return;
      const prod = this.products.find(p => p.id == this.selectedProduct);
      if (!prod) return;
      const existing = this.items.find(i => i.id == prod.id);
      if (existing) { existing.qty++; this.calcLine(this.items.indexOf(existing)); return; }
      const priceType = this.customerType;
      const unitPrice = priceType === 'wholesale' ? parseFloat(prod.wholesale_price) : parseFloat(prod.retail_price);
      this.items.push({
        id: prod.id, name: prod.name, sku: prod.sku, stock: prod.qty,
        qty: 1, unitPrice, priceType, total: unitPrice,
        retailPrice: parseFloat(prod.retail_price), wholesalePrice: parseFloat(prod.wholesale_price)
      });
      this.calcTotals();
    },

    updatePrice(idx) {
      const item = this.items[idx];
      item.unitPrice = item.priceType === 'wholesale' ? item.wholesalePrice : item.retailPrice;
      this.calcLine(idx);
    },

    calcLine(idx) {
      const item = this.items[idx];
      item.total = item.qty * item.unitPrice;
      this.calcTotals();
    },

    calcTotals() {
      this.subtotal = this.items.reduce((s, i) => s + i.total, 0);
      this.total    = Math.max(0, this.subtotal - (parseFloat(this.discount) || 0));
    },

    setCustomerName() {
      const sel = document.querySelector('select[name="customer_id"]');
      const opt = sel.options[sel.selectedIndex];
      this.customerName = opt.value ? opt.dataset.name : 'Walk-in Customer';
    },

    formatMoney(v) {
      return 'TSh ' + parseFloat(v || 0).toLocaleString('en-TZ', { minimumFractionDigits: 0 });
    },

    submitSale() {
      if (this.items.length === 0) { Toast.fire({icon:'warning',title:'Add at least one item'}); return; }
      this.$el.submit();
    },

    init() { if (this.branchId) this.loadProducts(); }
  }
}
</script>

<?php else: ?>
<!-- ── Sales List ── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <form method="GET" class="flex gap-2">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search invoice or customer..." class="form-input w-52">
    <?php if (isSuperAdmin() || hasRole('zone_manager')): ?>
    <select name="branch_id" onchange="this.form.submit()" class="form-input w-40 text-sm">
      <option value="">All Branches</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $filterBranch === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="btn-secondary"><i class="fas fa-search"></i></button>
    <?php if ($search || $filterBranch): ?><a href="/sales.php" class="btn-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
  </form>
  <a href="/sales.php?action=new" class="btn-primary"><i class="fas fa-plus"></i> New Sale</a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="table-th">Invoice</th>
          <th class="table-th">Customer</th>
          <th class="table-th">Branch</th>
          <th class="table-th">Payment</th>
          <th class="table-th text-right">Total</th>
          <th class="table-th">Status</th>
          <th class="table-th">Date</th>
          <th class="table-th text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($sales)): ?>
        <tr><td colspan="8" class="py-12 text-center text-gray-400">
          <i class="fas fa-receipt text-4xl mb-3 block"></i>
          No sales yet. <a href="/sales.php?action=new" class="text-indigo-600 underline">Create first sale</a>.
        </td></tr>
        <?php endif; ?>
        <?php foreach ($sales as $s): ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="table-td font-mono font-medium text-indigo-600"><?= e($s['invoice_no']) ?></td>
          <td class="table-td">
            <p><?= e($s['customer_name']) ?></p>
            <span class="badge <?= $s['customer_type']==='wholesale' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500' ?> text-xs capitalize"><?= e($s['customer_type']) ?></span>
          </td>
          <td class="table-td text-gray-500"><?= e($s['branch_name']) ?></td>
          <td class="table-td capitalize text-gray-500"><?= e(str_replace('_',' ',$s['payment_method'])) ?></td>
          <td class="table-td text-right font-semibold"><?= formatCurrency((float)$s['total']) ?></td>
          <td class="table-td">
            <span class="badge <?= $s['payment_status']==='paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?> capitalize"><?= e($s['payment_status']) ?></span>
          </td>
          <td class="table-td text-gray-400 text-xs"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
          <td class="table-td text-right">
            <a href="/sales.php?view=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-800 px-2 py-1 rounded hover:bg-indigo-50">
              <i class="fas fa-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
