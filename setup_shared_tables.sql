DROP TABLE IF EXISTS shared_invites;
DROP TABLE IF EXISTS shared_subscription_participants;
DROP TABLE IF EXISTS shared_subscriptions;
DROP TABLE IF EXISTS shared_members;
DROP TABLE IF EXISTS shared_plans;

CREATE TABLE shared_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    owner_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shared_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shared_plan_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    FOREIGN KEY (shared_plan_id) REFERENCES shared_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 