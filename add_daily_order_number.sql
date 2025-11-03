USE ice_cream_db;

-- Add daily_order_number column if it doesn't exist
ALTER TABLE orders ADD COLUMN IF NOT EXISTS daily_order_number int(11) NOT NULL AFTER order_id;

-- Add unique constraint for daily_order_number per date if it doesn't exist
ALTER TABLE orders 
ADD UNIQUE KEY IF NOT EXISTS unique_daily_order (daily_order_number, order_date);

-- Update existing orders with sequential daily numbers
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
SET o.daily_order_number = t.new_daily_number; 