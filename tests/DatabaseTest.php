<?php

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    public function testDatabaseConnection()
    {
        $database = new Database();
        $conn = $database->getConnection();
        $this->assertNotNull($conn, "Koneksi database harus berhasil");
    }

    public function testUsersTableExists()
    {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        $this->assertNotFalse($stmt->fetch(), "Tabel users harus ada");
    }
}
