<?php
// file: history.php
session_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penjualan Lumpia</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-back">← Kembali ke Kasir</a>
        
        <div class="header">
            <h1>📊 Riwayat Penjualan Lumpia</h1>
            <p>Data transaksi Lumpiakuu</p>
        </div>
        
        <?php 
        $total_transactions = isset($_SESSION['transactions']) ? count($_SESSION['transactions']) : 0;
        $total_revenue = 0;
        $total_items = 0;
        
        if (isset($_SESSION['transactions'])) {
            foreach ($_SESSION['transactions'] as $transaction) {
                $total_revenue += $transaction['total'];
                foreach ($transaction['items'] as $item) {
                    $total_items += $item['quantity'];
                }
            }
        }
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total Transaksi</h3>
                <div class="value"><?php echo $total_transactions; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Pendapatan</h3>
                <div class="value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Lumpia Terjual</h3>
                <div class="value"><?php echo number_format($total_items); ?> pcs</div>
            </div>
            <div class="stat-card">
                <h3>Rata-rata per Transaksi</h3>
                <div class="value">
                    Rp <?php echo $total_transactions > 0 ? number_format($total_revenue / $total_transactions, 0, ',', '.') : '0'; ?>
                </div>
            </div>
        </div>
        
        <?php if (empty($_SESSION['transactions'])): ?>
            <div class="history-card no-data">
                Belum ada transaksi<br>
                <a href="index.php">Mulai berjualan →</a>
            </div>
        <?php else: ?>
            <?php foreach ($_SESSION['transactions'] as $transaction): ?>
                <div class="history-card">
                    <div class="transaction-header">
                        <span class="transaction-id">🆔 <?php echo $transaction['id']; ?></span>
                        <span class="transaction-date">📅 <?php echo $transaction['date']; ?></span>
                        <span class="transaction-total">💰 Total: Rp <?php echo number_format($transaction['total'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Catatan</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaction['items'] as $item): ?>
                                <tr>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo !empty($item['note']) ? $item['note'] : '-'; ?></td>
                                    <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                    <td><?php echo $item['quantity']; ?> pcs</td>
                                    <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="transaction-footer">
                        <strong>💵 Bayar:</strong> Rp <?php echo number_format($transaction['payment'], 0, ',', '.'); ?> | 
                        <strong>🔄 Kembali:</strong> Rp <?php echo number_format($transaction['change'], 0, ',', '.'); ?> |
                        <strong>👤 Kasir:</strong> <?php echo $transaction['cashier']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>