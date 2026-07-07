<?php
// file: index.php
session_start();
require_once 'config/database.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Ambil data produk
$query = "SELECT * FROM products ORDER BY category, name";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inisialisasi keranjang
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Proses CRUD Produk
// Create Product
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $description = $_POST['description'];
    
    $query = "INSERT INTO products (name, category, price, stock, description) 
              VALUES (:name, :category, :price, :stock, :description)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':stock', $stock);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Produk berhasil ditambahkan!";
        header("Location: index.php");
        exit();
    }
}

// Update Product
if (isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $description = $_POST['description'];
    
    $query = "UPDATE products SET name=:name, category=:category, price=:price, 
              stock=:stock, description=:description WHERE id=:id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':stock', $stock);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Produk berhasil diupdate!";
        header("Location: index.php");
        exit();
    }
}

// Delete Product
if (isset($_GET['delete_product'])) {
    $id = $_GET['delete_product'];
    
    $query = "DELETE FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Produk berhasil dihapus!";
        header("Location: index.php");
        exit();
    }
}

// Tambah ke keranjang
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = intval($_POST['quantity']);
    $note = $_POST['note'] ?? '';
    
    // Cek stok
    $query = "SELECT * FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product && $product['stock'] >= $quantity) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'note' => $note
            ];
        }
        $_SESSION['success'] = "Berhasil ditambahkan ke keranjang!";
    } else {
        $_SESSION['error'] = "Stok tidak mencukupi!";
    }
    header("Location: index.php");
    exit();
}

// Update keranjang
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $id => $qty) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id]['quantity'] = $qty;
        }
    }
    header("Location: index.php");
    exit();
}

// Hapus dari keranjang
if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    unset($_SESSION['cart'][$id]);
    header("Location: index.php");
    exit();
}

// Proses checkout
if (isset($_POST['checkout'])) {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    $payment = floatval($_POST['payment']);
    $change = $payment - $total;
    
    if ($payment >= $total) {
        try {
            $db->beginTransaction();
            
            // Insert transaksi
            $invoice = 'INV/' . date('Ymd') . '/' . rand(100, 999);
            $query = "INSERT INTO transactions (invoice_number, user_id, total_amount, payment, change_amount) 
                      VALUES (:invoice, :user_id, :total, :payment, :change)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':invoice', $invoice);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':payment', $payment);
            $stmt->bindParam(':change', $change);
            $stmt->execute();
            
            $transaction_id = $db->lastInsertId();
            
            // Insert detail transaksi dan update stok
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $query = "INSERT INTO transaction_details (transaction_id, product_id, quantity, price, subtotal, note) 
                          VALUES (:trans_id, :product_id, :qty, :price, :subtotal, :note)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':trans_id', $transaction_id);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->bindParam(':qty', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':note', $item['note']);
                $stmt->execute();
                
                // Update stok
                $query = "UPDATE products SET stock = stock - :qty WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':qty', $item['quantity']);
                $stmt->bindParam(':id', $product_id);
                $stmt->execute();
            }
            
            $db->commit();
            
            $_SESSION['last_transaction'] = [
                'id' => $invoice,
                'date' => date('Y-m-d H:i:s'),
                'items' => $_SESSION['cart'],
                'total' => $total,
                'payment' => $payment,
                'change' => $change,
                'cashier' => $_SESSION['fullname']
            ];
            
            $_SESSION['cart'] = [];
            $_SESSION['success'] = "Transaksi berhasil!";
            header("Location: index.php?print=1");
            exit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Transaksi gagal: " . $e->getMessage();
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Pembayaran kurang!";
        header("Location: index.php");
        exit();
    }
}

// Hitung total keranjang
$grand_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $grand_total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Lumpia - Manajemen CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
            color: #f5a623 !important;
        }
        .product-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .modal-xl {
            max-width: 90%;
        }
        .btn-lumpia {
            background: #f5a623;
            color: white;
        }
        .btn-lumpia:hover {
            background: #d48a1a;
            color: white;
        }
        .receipt {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-egg"></i> Lumpiakuu
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-light me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['fullname']; ?> (<?php echo $_SESSION['role']; ?>)
                </span>
                <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="users.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-users"></i> Kelola Karyawan
                </a>
                <?php endif; ?>
                <a href="history.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-history"></i> Riwayat
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Kolom Produk -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-cubes"></i> Daftar Produk</h5>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($products as $product): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card product-card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo $product['name']; ?></h6>
                                        <span class="badge bg-info mb-2"><?php echo $product['category']; ?></span>
                                        <p class="card-text">
                                            <strong>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></strong><br>
                                            <small class="text-muted">Stok: <?php echo $product['stock']; ?> pcs</small>
                                        </p>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="number" name="quantity" value="1" min="1" 
                                                   max="<?php echo $product['stock']; ?>" class="form-control form-control-sm" style="width: 70px;">
                                            <input type="text" name="note" placeholder="Catatan" class="form-control form-control-sm">
                                            <button type="submit" name="add_to_cart" class="btn btn-sm btn-lumpia">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                            <?php if($_SESSION['role'] == 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo $product['name']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Keranjang -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if(empty($_SESSION['cart'])): ?>
                            <p class="text-muted text-center">Keranjang kosong</p>
                        <?php else: ?>
                            <form method="POST" id="cartForm">
                                <?php foreach($_SESSION['cart'] as $id => $item): ?>
                                <div class="cart-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo $item['name']; ?></strong><br>
                                            <?php if($item['note']): ?>
                                                <small class="text-muted">📝 <?php echo $item['note']; ?></small><br>
                                            <?php endif; ?>
                                            <small>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?> x 
                                                   <input type="number" name="quantity[<?php echo $id; ?>]" 
                                                          value="<?php echo $item['quantity']; ?>" 
                                                          style="width: 60px;" class="form-control form-control-sm d-inline">
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></strong><br>
                                            <a href="?remove=<?php echo $id; ?>" class="text-danger">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <hr>
                                <div class="text-end">
                                    <h5>Total: Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></h5>
                                </div>
                                <button type="submit" name="update_cart" class="btn btn-secondary w-100 mb-2">
                                    <i class="fas fa-sync-alt"></i> Update Keranjang
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if(!empty($_SESSION['cart'])): ?>
                    <div class="card-footer">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Jumlah Bayar</label>
                                <input type="number" name="payment" class="form-control" required step="1000">
                            </div>
                            <button type="submit" name="checkout" class="btn btn-success w-100">
                                <i class="fas fa-check"></i> Proses Pembayaran
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Produk -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Produk Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Produk</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Kategori</label>
                            <select name="category" class="form-control" required>
                                <option value="Basah">Lumpia Basah</option>
                                <option value="Goreng">Lumpia Goreng</option>
                                <option value="Mini">Lumpia Mini</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Harga</label>
                            <input type="number" name="price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Stok</label>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Produk -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="product_id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Produk</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Kategori</label>
                            <select name="category" id="edit_category" class="form-control" required>
                                <option value="Basah">Lumpia Basah</option>
                                <option value="Goreng">Lumpia Goreng</option>
                                <option value="Mini">Lumpia Mini</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Harga</label>
                            <input type="number" name="price" id="edit_price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Stok</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Deskripsi</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_product" class="btn btn-warning">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Struk -->
    <?php if(isset($_GET['print']) && isset($_SESSION['last_transaction'])): ?>
    <div class="modal show" id="receiptModal" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body receipt">
                    <div class="text-center">
                        <h5>🥟 LUMPIAKU</h5>
                        <small>Jl. bojong No. 123</small><br>
                        <small><?php echo date('d/m/Y H:i:s'); ?></small>
                        <hr>
                    </div>
                    <?php foreach($_SESSION['last_transaction']['items'] as $item): ?>
                        <div>
                            <?php echo $item['name']; ?>
                            <?php if($item['note']): ?><br><small>  <?php echo $item['note']; ?></small><?php endif; ?>
                            <br>
                            <?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                            <span class="float-end">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <p>Total<span class="float-end">Rp <?php echo number_format($_SESSION['last_transaction']['total'], 0, ',', '.'); ?></span></p>
                    <p>Bayar<span class="float-end">Rp <?php echo number_format($_SESSION['last_transaction']['payment'], 0, ',', '.'); ?></span></p>
                    <p>Kembali<span class="float-end">Rp <?php echo number_format($_SESSION['last_transaction']['change'], 0, ',', '.'); ?></span></p>
                    <hr>
                    <p>Kasir: <?php echo $_SESSION['last_transaction']['cashier']; ?></p>
                    <p class="text-center">Terima kasih!<br>Simpan struk ini</p>
                </div>
                <div class="modal-footer">
                    <button onclick="window.print()" class="btn btn-primary w-100">🖨️ Cetak Struk</button>
                    <button onclick="window.location.href='index.php'" class="btn btn-secondary w-100 mt-2">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_description').value = product.description;
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
        
        function deleteProduct(id, name) {
            if(confirm(`Yakin hapus produk "${name}"?`)) {
                window.location.href = `?delete_product=${id}`;
            }
        }
        
        // Auto refresh saat stok berubah
        setTimeout(() => {
            location.reload();
        }, 300000); // Reload setiap 5 menit
    </script>
</body>
</html>