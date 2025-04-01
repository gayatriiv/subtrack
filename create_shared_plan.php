<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Fetch user's subscriptions
try {
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM subscriptions 
        WHERE user_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll();

    // Fetch categories
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $subscriptions = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Shared Plan - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .create-plan-form {
            max-width: 600px;
            margin: 0 auto;
        }

        .member-list {
            margin: 20px 0;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .remove-member {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            padding: 5px;
        }

        .add-member-btn {
            background: none;
            border: none;
            color: #4CAF50;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 0;
            font-size: 14px;
        }

        .cost-split {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .split-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .split-option.active {
            background: #4CAF50;
            color: white;
        }

        .form-section {
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }

        .form-section h3 {
            margin-bottom: 15px;
            color: #4CAF50;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .member-shares {
            display: none;
            margin-top: 15px;
        }

        .member-share-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .member-email {
            flex: 2;
        }

        .member-percentage {
            flex: 1;
        }

        .total-percentage {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            text-align: right;
        }

        .total-percentage.error {
            color: #f44336;
        }

        .preview-section {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            display: none;
        }

        .preview-section h3 {
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .preview-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .preview-item {
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }

        .preview-label {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .preview-value {
            font-size: 16px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            color: white;
            transition: border-color 0.3s, background 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-control option {
            background: #2a2a2a;
            color: white;
        }

        /* Error styles */
        .error-message {
            color: #f44336;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-control.error {
            border-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="create-plan-form">
                <h2>Create Shared Plan</h2>
                <form action="process_shared_plan.php" method="POST" id="sharedPlanForm">
                    <div class="form-section">
                        <h3>Subscription Details</h3>
                        <div class="form-group">
                            <label for="subscription_type">Subscription Type</label>
                            <select name="subscription_type" id="subscription_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="existing">Use Existing Subscription</option>
                                <option value="new">Create New Subscription</option>
                            </select>
                        </div>

                        <div id="existingSubscriptionFields" style="display: none;">
                            <div class="form-group">
                                <label for="existing_subscription">Select Subscription</label>
                                <select name="existing_subscription" id="existing_subscription" class="form-control">
                                    <?php if (empty($subscriptions)): ?>
                                        <option value="">No subscriptions available</option>
                                    <?php else: ?>
                                        <option value="">Select Subscription</option>
                                        <?php foreach ($subscriptions as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div id="newSubscriptionFields" style="display: none;">
                            <div class="form-group">
                                <label for="name">Subscription Name</label>
                                <input type="text" id="name" name="name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select name="category" id="category" class="form-control">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cost">Total Cost</label>
                                <input type="number" id="cost" name="cost" step="0.01" class="form-control" min="0">
                            </div>
                            <div class="form-group">
                                <label for="billing_cycle">Billing Cycle</label>
                                <select name="billing_cycle" id="billing_cycle" class="form-control">
                                    <option value="">Select Billing Cycle</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Cost Splitting</h3>
                        <div class="cost-split">
                            <div class="split-option active" data-split="equal">
                                <i class="fas fa-equals"></i>
                                <div>Split Equally</div>
                            </div>
                            <div class="split-option" data-split="custom">
                                <i class="fas fa-sliders-h"></i>
                                <div>Custom Split</div>
                            </div>
                        </div>
                        <input type="hidden" name="split_type" id="split_type" value="equal">
                        
                        <div id="memberShares" class="member-shares">
                            <div class="member-share-item">
                                <div class="member-email">You (Owner)</div>
                                <div class="member-percentage">
                                    <input type="number" name="shares[]" class="form-control share-input" value="100" min="0" max="100">%
                                </div>
                            </div>
                        </div>
                        <div id="totalPercentage" class="total-percentage">
                            Total: <span>100</span>%
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Add Members</h3>
                        <div class="member-list" id="memberList">
                            <div class="member-item">
                                <input type="email" name="members[]" class="form-control" placeholder="Enter email address" required>
                                <button type="button" class="remove-member" onclick="removeMember(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="add-member-btn" onclick="addMember()">
                            <i class="fas fa-plus"></i> Add Another Member
                        </button>
                    </div>

                    <div id="previewSection" class="preview-section">
                        <h3>Subscription Preview</h3>
                        <div class="preview-details">
                            <div class="preview-item">
                                <div class="preview-label">Subscription Name</div>
                                <div class="preview-value" id="previewName">-</div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-label">Total Cost</div>
                                <div class="preview-value" id="previewCost">-</div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-label">Billing Cycle</div>
                                <div class="preview-value" id="previewCycle">-</div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-label">Your Share</div>
                                <div class="preview-value" id="previewShare">-</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Shared Plan</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('sharedPlanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const subscriptionType = document.getElementById('subscription_type').value;
            let isValid = true;
            let errorMessage = '';

            // Validate subscription type selection
            if (!subscriptionType) {
                isValid = false;
                errorMessage = 'Please select a subscription type';
            }

            // Validate existing subscription
            if (subscriptionType === 'existing') {
                const existingSubscription = document.getElementById('existing_subscription').value;
                if (!existingSubscription) {
                    isValid = false;
                    errorMessage = 'Please select a subscription';
                }
            }

            // Validate new subscription fields
            if (subscriptionType === 'new') {
                const name = document.getElementById('name').value;
                const category = document.getElementById('category').value;
                const cost = document.getElementById('cost').value;
                const billingCycle = document.getElementById('billing_cycle').value;

                if (!name || !category || !cost || !billingCycle) {
                    isValid = false;
                    errorMessage = 'Please fill in all subscription details';
                }
            }

            // Validate members
            const members = document.getElementsByName('members[]');
            let hasValidMember = false;
            for (let member of members) {
                if (member.value && member.value.trim()) {
                    hasValidMember = true;
                    break;
                }
            }

            if (!hasValidMember) {
                isValid = false;
                errorMessage = 'Please add at least one member';
            }

            // Validate custom split if selected
            const splitType = document.getElementById('split_type').value;
            if (splitType === 'custom') {
                const shares = Array.from(document.getElementsByClassName('share-input')).map(input => Number(input.value));
                const total = shares.reduce((a, b) => a + b, 0);
                if (Math.abs(total - 100) > 0.01) {
                    isValid = false;
                    errorMessage = 'Share percentages must total 100%';
                }
            }

            if (!isValid) {
                alert(errorMessage);
                return;
            }

            // If all validation passes, submit the form
            this.submit();
        });

        // Toggle subscription fields with validation
        document.getElementById('subscription_type').addEventListener('change', function() {
            const existingFields = document.getElementById('existingSubscriptionFields');
            const newFields = document.getElementById('newSubscriptionFields');
            
            existingFields.style.display = 'none';
            newFields.style.display = 'none';
            
            if (this.value === 'existing') {
                existingFields.style.display = 'block';
                // Reset new subscription fields
                document.getElementById('name').value = '';
                document.getElementById('category').value = '';
                document.getElementById('cost').value = '';
                document.getElementById('billing_cycle').value = '';
            } else if (this.value === 'new') {
                newFields.style.display = 'block';
                // Reset existing subscription field
                document.getElementById('existing_subscription').value = '';
            }
            
            updatePreview();
        });

        // Handle split options
        document.querySelectorAll('.split-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.split-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('split_type').value = this.dataset.split;
                
                const memberShares = document.getElementById('memberShares');
                if (this.dataset.split === 'custom') {
                    memberShares.style.display = 'block';
                    updateShares();
                } else {
                    memberShares.style.display = 'none';
                    updatePreview();
                }
            });
        });

        // Add member field with share percentage
        function addMember() {
            const memberList = document.getElementById('memberList');
            const memberShares = document.getElementById('memberShares');
            const newMember = document.createElement('div');
            newMember.className = 'member-item';
            
            const emailInput = document.createElement('input');
            emailInput.type = 'email';
            emailInput.name = 'members[]';
            emailInput.className = 'form-control';
            emailInput.placeholder = 'Enter email address';
            emailInput.required = true;
            emailInput.addEventListener('input', updateShares);

            const shareItem = document.createElement('div');
            shareItem.className = 'member-share-item';
            const shareInput = document.createElement('input');
            shareInput.type = 'number';
            shareInput.name = 'shares[]';
            shareInput.className = 'form-control share-input';
            shareInput.value = '0';
            shareInput.min = '0';
            shareInput.max = '100';
            shareInput.addEventListener('input', updateShares);

            newMember.appendChild(emailInput);
            newMember.appendChild(document.createElement('button'));
            newMember.lastChild.type = 'button';
            newMember.lastChild.className = 'remove-member';
            newMember.lastChild.innerHTML = '<i class="fas fa-times"></i>';
            newMember.lastChild.onclick = function() { removeMember(this); };

            shareItem.innerHTML = `
                <div class="member-email"></div>
                <div class="member-percentage">
                    <input type="number" name="shares[]" class="form-control share-input" value="0" min="0" max="100">%
                </div>
            `;

            memberList.appendChild(newMember);
            memberShares.appendChild(shareItem);
            updateShares();
        }

        // Remove member field and share
        function removeMember(button) {
            const memberList = document.getElementById('memberList');
            const memberShares = document.getElementById('memberShares');
            if (memberList.children.length > 1) {
                const index = Array.from(memberList.children).indexOf(button.parentElement);
                memberList.children[index].remove();
                memberShares.children[index + 1].remove(); // +1 because of the owner row
                updateShares();
            }
        }

        // Update shares and preview
        function updateShares() {
            const memberList = document.getElementById('memberList');
            const memberShares = document.getElementById('memberShares');
            const splitType = document.getElementById('split_type').value;
            
            // Update member emails in share items
            const memberEmails = Array.from(memberList.children).map(item => 
                item.querySelector('input[type="email"]').value || 'Pending Member'
            );
            
            // Update share items to match member count
            while (memberShares.children.length - 1 < memberEmails.length) {
                const shareItem = document.createElement('div');
                shareItem.className = 'member-share-item';
                shareItem.innerHTML = `
                    <div class="member-email"></div>
                    <div class="member-percentage">
                        <input type="number" name="shares[]" class="form-control share-input" value="0" min="0" max="100">%
                    </div>
                `;
                memberShares.appendChild(shareItem);
            }
            
            // Remove extra share items
            while (memberShares.children.length - 1 > memberEmails.length) {
                memberShares.lastChild.remove();
            }
            
            // Update member emails in share items
            const shareItems = memberShares.children;
            shareItems[0].querySelector('.member-email').textContent = 'You (Owner)';
            for (let i = 1; i < shareItems.length; i++) {
                shareItems[i].querySelector('.member-email').textContent = memberEmails[i-1];
            }
            
            if (splitType === 'equal') {
                const equalShare = Math.floor(100 / (memberEmails.length + 1));
                const remainder = 100 - (equalShare * (memberEmails.length + 1));
                
                Array.from(shareItems).forEach((item, index) => {
                    const input = item.querySelector('.share-input');
                    input.value = equalShare + (index === 0 ? remainder : 0);
                    input.disabled = true;
                });
            } else {
                Array.from(shareItems).forEach(item => {
                    const input = item.querySelector('.share-input');
                    input.disabled = false;
                });
            }
            
            // Calculate total and update display
            const shares = Array.from(document.getElementsByClassName('share-input')).map(input => Number(input.value));
            const total = shares.reduce((a, b) => a + b, 0);
            const totalElement = document.getElementById('totalPercentage');
            
            totalElement.innerHTML = `Total: <span>${total}</span>%`;
            totalElement.className = 'total-percentage' + (total !== 100 ? ' error' : '');
            
            updatePreview();
        }

        // Update preview section
        function updatePreview() {
            const previewSection = document.getElementById('previewSection');
            const subscriptionType = document.getElementById('subscription_type').value;
            let subscriptionName = '-';
            let cost = '0';
            let cycle = 'monthly';
            
            if (subscriptionType === 'existing') {
                const select = document.getElementById('existing_subscription');
                if (select.value) {
                    subscriptionName = select.options[select.selectedIndex].text;
                    // Fetch cost and cycle from the server or data attribute
                }
            } else if (subscriptionType === 'new') {
                subscriptionName = document.getElementById('name').value || '-';
                cost = document.getElementById('cost').value || '0';
                cycle = document.getElementById('billing_cycle').value || 'monthly';
            }

            const splitType = document.getElementById('split_type').value;
            const totalCost = parseFloat(cost);
            let yourShare = 0;

            if (splitType === 'equal') {
                const memberCount = document.getElementById('memberList').children.length + 1; // +1 for owner
                yourShare = totalCost / memberCount;
            } else {
                const yourPercentage = parseFloat(document.querySelector('.share-input').value) || 0;
                yourShare = (totalCost * yourPercentage) / 100;
            }

            document.getElementById('previewName').textContent = subscriptionName;
            document.getElementById('previewCost').textContent = `$${totalCost.toFixed(2)}`;
            document.getElementById('previewCycle').textContent = cycle.charAt(0).toUpperCase() + cycle.slice(1);
            document.getElementById('previewShare').textContent = `$${yourShare.toFixed(2)}`;
            previewSection.style.display = 'block';
        }

        // Add event listeners for preview updates
        ['subscription_type', 'existing_subscription', 'name', 'cost', 'billing_cycle'].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', updatePreview);
                element.addEventListener('input', updatePreview);
            }
        });
    </script>
</body>
</html> 