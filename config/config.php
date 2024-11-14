<?php
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'backup_user',
        'password' => 'IhrSicheresPasswort',
        'database' => 'backup_monitor'
    ],
    
    'mail' => [
        'server' => 'pop.example.com',
        'port' => 995,
        'username' => 'mail@example.com',
        'password' => 'IhrMailPasswort',
        'ssl' => true,
        'delete_after_processing' => false
    ],
    
    'app' => [
        'name' => 'Backup Monitor',
        'timezone' => 'Europe/Berlin',
        'log_path' => __DIR__ . '/../logs',
        'debug' => false,
        'retention_days' => 30,
    ],
    
    'security' => [
        'allowed_ips' => [
            '127.0.0.1',
            '::1',
            // Weitere erlaubte IP-Adressen
        ]
    ],
    
    'installation_date' => '2024-03-14 12:00:00'
];