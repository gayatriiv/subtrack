-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT
);

-- Create subscriptions table
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    next_payment_date DATE NOT NULL,
    billing_cycle VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Create shared_subscriptions table
CREATE TABLE shared_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    total_participants INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create shared_subscription_participants table
CREATE TABLE shared_subscription_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shared_subscription_id INT NOT NULL,
    user_id INT NOT NULL,
    user_share DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (shared_subscription_id) REFERENCES shared_subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE budget_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    monthly_budget DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Streaming', 'Video and music streaming services'),
('Gaming', 'Gaming subscriptions and services'),
('News', 'News and magazine subscriptions'),
('Software', 'Software and app subscriptions'),
('Fitness', 'Fitness and wellness subscriptions'),
('Education', 'Educational platform subscriptions'),
('Other', 'Other miscellaneous subscriptions'); 
