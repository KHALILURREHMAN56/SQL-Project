<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Drop the existing foreign key constraint
    $pdo->exec("ALTER TABLE orders DROP FOREIGN KEY IF EXISTS orders_ibfk_1");
    
    // Modify the attendant_id column to reference users table instead
    $pdo->exec("ALTER TABLE orders DROP INDEX IF EXISTS attendant_id");
    $pdo->exec("ALTER TABLE orders ADD CONSTRAINT orders_user_fk FOREIGN KEY (attendant_id) REFERENCES users(user_id)");
    
    // Add daily_order_number column
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS daily_order_number int(11) NOT NULL AFTER order_id");
    
    // Add unique constraint
    $pdo->exec("ALTER TABLE orders ADD UNIQUE KEY IF NOT EXISTS unique_daily_order (daily_order_number, order_date)");
    
    // Initialize daily_order_number for existing orders
    $pdo->exec("
        SET @row_number = 0;
        SET @current_date = NULL;
        
        UPDATE orders o
        JOIN (
            SELECT 
                order_id,
                order_date,
                @row_number := IF(DATE(order_date) = @current_date, @row_number + 1, 1) as new_daily_number,
                @current_date := DATE(order_date)
            FROM orders
            ORDER BY order_date
        ) t ON o.order_id = t.order_id
        SET o.daily_order_number = t.new_daily_number
    ");
    
    echo "Successfully updated orders table structure!";
    
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?> 