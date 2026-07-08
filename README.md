# Lumpia

Aplikasi web berbasis PHP untuk manajemen data.

## Fitur

- Login/Logout user
- Manajemen pengguna
- History aktivitas
- CRUD data

## Persyaratan

- PHP >= 7.4
- MySQL/MariaDB
- Web server (Apache/XAMPP)

## Instalasi

1. Clone repositori ini ke folder `htdocs` XAMPP
2. Import database dari `config/database.php`
3. Sesuaikan konfigurasi database di `config/database.php`
4. Akses `http://localhost/lumpia`

## Struktur Folder

```
lumpia/
├── config/         # Konfigurasi database
├── index.php       # Halaman utama
├── login.php       # Halaman login
├── logout.php      # Logout
├── users.php       # Manajemen user
├── history.php     # History aktivitas
└── style.css       # Styling
```
