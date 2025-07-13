<?php

/**
 * Script para criar usuário MASTER diretamente no banco SQLite
 * Execute: php create_master_user.php
 */

$dbPath = __DIR__ . '/database/database.sqlite';

// Verificar se o arquivo SQLite existe
if (!file_exists($dbPath)) {
    echo "❌ Arquivo do banco de dados não encontrado: $dbPath\n";
    echo "Criando arquivo SQLite...\n";
    touch($dbPath);
}

try {
    // Conectar ao SQLite
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se a tabela users_ddd existe
    $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users_ddd'")->fetch();
    
    if (!$tableExists) {
        echo "❌ Tabela 'users_ddd' não existe. Execute as migrations primeiro.\n";
        echo "💡 Criando estrutura básica da tabela...\n";
        
        // Criar tabela básica se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users_ddd (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                hierarchy_role TEXT DEFAULT 'STORE_MANAGER',
                status TEXT DEFAULT 'active',
                organization_id TEXT,
                store_id TEXT,
                phone TEXT,
                permissions TEXT,
                context_data TEXT,
                email_verified_at TEXT,
                last_login_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");
    }

    // Gerar UUID simples (sem biblioteca externa)
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $email = 'master@madnezz.com';
    $password = 'Master@123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');

    // Verificar se usuário já existe
    $stmt = $pdo->prepare("SELECT id FROM users_ddd WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        echo "⚠️ Usuário MASTER já existe. Atualizando...\n";
        
        $stmt = $pdo->prepare("
            UPDATE users_ddd SET 
                name = ?, 
                password = ?, 
                hierarchy_role = ?, 
                status = ?,
                phone = ?,
                permissions = ?,
                email_verified_at = ?,
                updated_at = ?
            WHERE email = ?
        ");
        
        $stmt->execute([
            'Master Admin',
            $hashedPassword,
            'MASTER',
            'active',
            '+55 11 99999-9999',
            json_encode(['*']),
            $now,
            $now,
            $email
        ]);
    } else {
        echo "🔄 Criando novo usuário MASTER...\n";
        
        $stmt = $pdo->prepare("
            INSERT INTO users_ddd (
                id, name, email, password, hierarchy_role, status, 
                organization_id, store_id, phone, permissions, context_data,
                email_verified_at, last_login_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $uuid,
            'Master Admin',
            $email,
            $hashedPassword,
            'MASTER',
            'active',
            null,
            null,
            '+55 11 99999-9999',
            json_encode(['*']),
            null,
            $now,
            null,
            $now,
            $now
        ]);
    }

    echo "\n";
    echo "════════════════════════════════════════\n";
    echo "✅ USUÁRIO MASTER CRIADO COM SUCESSO!\n";
    echo "════════════════════════════════════════\n";
    echo "📧 Email: $email\n";
    echo "🔒 Senha: $password\n";
    echo "👑 Papel: MASTER (acesso total)\n";
    echo "🆔 ID: $uuid\n";
    echo "📱 Telefone: +55 11 99999-9999\n";
    echo "════════════════════════════════════════\n";
    echo "\n";
    echo "💡 Use essas credenciais para fazer login no sistema.\n";
    echo "💡 O usuário MASTER pode:\n";
    echo "   - Criar organizações\n";
    echo "   - Criar lojas\n";
    echo "   - Assumir qualquer papel (context switching)\n";
    echo "   - Acessar todos os dados do sistema\n";
    echo "\n";

} catch (PDOException $e) {
    echo "❌ Erro ao conectar com o banco: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
    exit(1);
}