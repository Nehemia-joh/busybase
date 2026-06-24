<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
requireRole('super_admin','zone_manager','branch_manager','stock_controller');

$db        = getDB();
$pageTitle = 'Supplier Management';
$action    = clean($_GET['action'] ?? 'list');
$editId    = cleanInt($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = clean($_POST['_act'] ?? '');

    if ($act === 'create' || $act === 'update') {
        $sid         = cleanInt($_POST['supplier_id'] ?? 0);
        $name        = clean($_POST['name'] ?? '');
        $company     = clean($_POST['company_name'] ?? '');
        $contact     = clean($_POST['contact_person'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = filter_var(clean($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        $address     = clean($_POST['address'] ?? '');
        $taxId       = clean($_POST['tax_id'] ?? '');
        $payTerms    = clean($_POST['payment_terms'] ?? '');
        $status      = clean($_POST['status'] ?? 'active');
        $notes       = clean($_POST['notes'] ?? '');

        if (!$name) { flash('error','Supplier name is required.'); header('Location: /suppliers?action=' . ($act==='create'?'new':"edit&id=$sid")); exit; }

        if ($act === 'create') {
            $db->prepare('INSERT INTO suppliers (name,company_name,contact_person,phone,email,address,tax_id,payment_terms,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$name,$company,$contact,$phone,$email,$address,$taxId,$payTerms,$status,$notes]);
            logActivity('supplier_created', "Created supplier: $name");
            flash('success', "Supplier '$name' created.");
        } else {
            $db->prepare('UPDATE suppliers SET name=?,company_name=?,contact_person=?,phone=?,email=?,address=?,tax_id=?,payment_terms=?,status=?,notes=? WHERE id=?')->execute([$name,$company,$contact,$phone,$email,$address,$taxId,$payTerms,$status,$notes,$sid]);
            logActivity('supplier_updated', "Updated supplier: $name");
            flash('success', "Supplier '$name' updated.");
        }
        header('Location: /suppliers'); exit;
    }

    if ($act === 'delete') {
        $sid = cleanInt($_POST['supplier_id'] ?? 0);
        $poCount = (function() use ($db,$sid) { $s=$db->prepare('SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=?'); $s->execute([$sid]); return (int)$s->fetchColumn(); })();
        if ($poCount > 0) {
            flash('error', "Cannot delete: supplier has $poCount purchase order(s).");
        } else {
            $db->prepare('DELETE FROM suppliers WHERE id=?')->execute([$sid]);
            logActivity('supplier_deleted', "Deleted supplier ID: $sid");
            flash('success', 'Supplier deleted.');
        }
        header('Location: /suppliers'); exit;
    }
}

$editSupplier = null;
if ($action === 'edit' && $editId) {
    $s = $db->prepare('SELECT * FROM suppliers WHERE id=?'); $s->execute([$editId]); $editSupplier = $s->fetch();
    if (!$editSupplier) { header('Location: /suppliers'); exit; }
}

$search = clean($_GET['q'] ?? '');
$whereStr = $search ? 'WHERE name LIKE ? OR company_name LIKE ?' : '';
$params   = $search ? ["%$search%", "%$search%"] : [];
$stmt     = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id=s.id) as po_count FROM suppliers s $whereStr ORDER BY s.name");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

include __DIR__ . '/includes/tailwind.php';
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center gap-3 mb-6">
    <a href="/suppliers.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h2 class="text-lg font-semibold text-gray-800"><?= $action === 'new' ? 'Add Supplier' : 'Edit Supplier' ?></h2>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="_act" value="<?= $action==='new'?'create':'update' ?>">
      <?php if ($editSupplier): ?><input type="hidden" name="supplier_id" value="<?= $editSupplier['id'] ?>"><?php endif; ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Supplier Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" required class="form-input" value="<?= e($editSupplier['name'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Company Name</label>
          <input type="text" name="company_name" class="form-input" value="<?= e($editSupplier['company_name'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Contact Person</label>
          <input type="text" name="contact_person" class="form-input" value="<?= e($editSupplier['contact_person'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-input" value="<?= e($editSupplier['phone'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input" value="<?= e($editSupplier['email'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Tax ID</label>
          <input type="text" name="tax_id" class="form-input" value="<?= e($editSupplier['tax_id'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Payment Terms</label>
          <input type="text" name="payment_terms" class="form-input" placeholder="e.g. Net 30" value="<?= e($editSupplier['payment_terms'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Status</label>
          <select name="status" class="form-input">
            <option value="active" <?= ($editSupplier['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($editSupplier['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="form-label">Address</label>
          <textarea name="address" rows="2" class="form-input"><?= e($editSupplier['address'] ?? '') ?></textarea>
        </div>
        <div class="sm:col-span-2">
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-input"><?= e($editSupplier['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <?= $action==='new'?'Create Supplier':'Save Changes' ?></button>
        <a href="/suppliers.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <form method="GET" class="flex gap-2">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search suppliers..." class="form-input w-52">
    <button type="submit" class="btn-secondary"><i class="fas fa-search"></i></button>
    <?php if ($search): ?><a href="/suppliers.php" class="btn-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
  </form>
  <a href="/suppliers.php?action=new" class="btn-primary"><i class="fas fa-plus"></i> Add Supplier</a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="table-th">Supplier</th>
          <th class="table-th">Contact</th>
          <th class="table-th">Phone / Email</th>
          <th class="table-th">Payment Terms</th>
          <th class="table-th text-center">POs</th>
          <th class="table-th">Status</th>
          <th class="table-th text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($suppliers)): ?>
        <tr><td colspan="7" class="py-10 text-center text-gray-400">No suppliers found</td></tr>
        <?php endif; ?>
        <?php foreach ($suppliers as $s): ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="table-td">
            <p class="font-medium text-gray-800"><?= e($s['name']) ?></p>
            <?= $s['company_name'] ? '<p class="text-xs text-gray-400">' . e($s['company_name']) . '</p>' : '' ?>
          </td>
          <td class="table-td text-gray-500"><?= $s['contact_person'] ? e($s['contact_person']) : '<span class="text-gray-300">—</span>' ?></td>
          <td class="table-td text-gray-500 text-xs">
            <?= $s['phone'] ? '<div>' . e($s['phone']) . '</div>' : '' ?>
            <?= $s['email'] ? '<div>' . e($s['email']) . '</div>' : '' ?>
          </td>
          <td class="table-td text-gray-500"><?= $s['payment_terms'] ?: '<span class="text-gray-300">—</span>' ?></td>
          <td class="table-td text-center"><span class="badge bg-blue-100 text-blue-700"><?= $s['po_count'] ?></span></td>
          <td class="table-td">
            <span class="badge <?= $s['status']==='active'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> capitalize"><?= e($s['status']) ?></span>
          </td>
          <td class="table-td text-right">
            <div class="flex justify-end gap-2">
              <a href="/suppliers.php?action=edit&id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-800 px-2 py-1 rounded hover:bg-indigo-50"><i class="fas fa-edit"></i></a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete this supplier?')">
                <?= csrf_field() ?>
                <input type="hidden" name="_act" value="delete">
                <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                <button type="submit" class="text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
