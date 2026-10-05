<?php
$currentPage = 'budget-planner';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

$pageTitle = 'Budget Planner - Holiday Food Spending Tool';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #fef5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .budget-planner-container {
            padding: 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Tab Navigation */
        .tabs-container {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: #f3f4f6;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            color: #6b7280;
            text-align: center;
            min-width: 120px;
        }

        .tab:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .tab.active {
            background: #fef5f5;
            border-color: #c0392b;
            color: #c0392b;
            font-weight: 600;
        }

        .tab.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .tab .tab-number {
            font-size: 0.85rem;
            display: block;
            margin-bottom: 0.25rem;
        }

        .tab .tab-label {
            font-size: 0.9rem;
        }

        /* Steps visibility */
        .step-container {
            display: none;
        }

        .step-container.active {
            display: block;
        }

        /* Card styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #fee;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            background: #dc2626;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .card-title-area h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 0.25rem 0;
        }

        .card-title-area p {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
            line-height: 1.5;
        }

        /* Info box */
        .info-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
        }

        .info-box-icon {
            color: #dc2626;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .info-box-text {
            color: #991b1b;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #dc2626;
        }

        .form-input-icon {
            position: relative;
        }

        .form-input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .form-input-icon .form-input {
            padding-left: 2.5rem;
        }

        .form-hint {
            font-size: 0.875rem;
            color: #dc2626;
            margin-top: 0.25rem;
        }

        /* Budget summary boxes */
        .budget-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 1.5rem;
            background: #fef5f5;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .budget-summary-item {
            text-align: center;
        }

        .budget-summary-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .budget-summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        /* Progress bar */
        .progress-container {
            margin: 1.5rem 0;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .progress-bar {
            height: 24px;
            background: #f3f4f6;
            border-radius: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc2626, #ef4444);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 0.5rem;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Food items list */
        .food-items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .food-items-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .food-item-row {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.2fr 0.8fr 40px;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .food-item-row:last-child {
            border-bottom: none;
        }

        .food-item-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        /* Totals box */
        .totals-box {
            background: #fef5f5;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .totals-label {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .totals-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        /* Big red box (Holiday AFC) */
        .afc-box {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin: 2rem 0;
        }

        .afc-box-header {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .afc-box-value {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .afc-box-note {
            font-size: 0.875rem;
            opacity: 0.9;
            line-height: 1.5;
        }

        /* Results cards */
        .result-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .result-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .result-card.highlight {
            background: #ecfdf5;
            border-color: #10b981;
        }

        .result-card.danger {
            background: #fee2e2;
            border-color: #dc2626;
        }

        .result-card.danger .result-card-value {
            color: #dc2626;
        }

        .result-card-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .result-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }

        .result-card.highlight .result-card-value {
            color: #059669;
        }

        .result-card-sub {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Calculation box */
        .calc-box {
            background: #fef5f5;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .calc-box h3 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 1rem 0;
        }

        .calc-formula {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.8;
            color: #374151;
        }

        /* Breakdown table */
        .breakdown-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .breakdown-table th {
            text-align: left;
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            padding-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .breakdown-table td {
            padding: 1rem;
            background: #fef5f5;
            font-size: 0.875rem;
        }

        .breakdown-table td:first-child {
            border-radius: 8px 0 0 8px;
        }

        .breakdown-table td:last-child {
            border-radius: 0 8px 8px 0;
        }

        .breakdown-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .breakdown-bar-fill {
            height: 100%;
            border-radius: 4px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-icon {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.1rem;
        }

        .btn-icon:hover {
            color: #b91c1c;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-add {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add:hover {
            background: #fecaca;
        }

        /* Success box */
        .success-box {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .success-box-icon {
            width: 48px;
            height: 48px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .success-box-content h3 {
            margin: 0 0 0.25rem 0;
            color: #065f46;
            font-size: 1.1rem;
        }

        .success-box-content p {
            margin: 0;
            color: #059669;
            font-size: 0.9rem;
        }

        /* Tips box */
        .tips-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .tips-box h3 {
            margin: 0 0 1rem 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .tips-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips-list li {
            padding: 0.75rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .tips-list li i {
            color: #10b981;
            margin-top: 0.25rem;
        }

        /* History table */
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            text-align: left;
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 700;
            padding: 1rem;
            background: #fef5f5;
            text-transform: uppercase;
        }

        .history-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }

        .history-table tbody tr:hover {
            background: #fef5f5;
        }

        @media (max-width: 768px) {
            .budget-planner-container {
                padding: 1rem;
            }

            .tabs {
                gap: 0.25rem;
            }

            .tab {
                padding: 0.5rem 0.75rem;
                min-width: 90px;
                font-size: 0.85rem;
            }

            .tab .tab-number {
                font-size: 0.75rem;
            }

            .tab .tab-label {
                font-size: 0.8rem;
            }

            .food-item-row {
                grid-template-columns: 1fr;
            }

            .budget-summary,
            .result-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="budget-planner-container">
        
        <!-- Tab Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <div class="tab active" onclick="goToStep(1)" id="tab1">
                    <span class="tab-number">Step 1</span>
                    <span class="tab-label">Budget Setting</span>
                </div>
                <div class="tab" onclick="goToStep(2)" id="tab2">
                    <span class="tab-number">Step 2</span>
                    <span class="tab-label">Food Planning</span>
                </div>
                <div class="tab" onclick="goToStep(3)" id="tab3">
                    <span class="tab-number">Step 3</span>
                    <span class="tab-label">Actual Spending</span>
                </div>
                <div class="tab" onclick="goToStep(4)" id="tab4">
                    <span class="tab-number">Step 4</span>
                    <span class="tab-label">Results</span>
                </div>
                <div class="tab" onclick="goToStep(5)" id="tab5">
                    <span class="tab-number">Step 5</span>
                    <span class="tab-label">Breakdown</span>
                </div>
                <div class="tab" onclick="goToStep(6)" id="tab6">
                    <span class="tab-number">Step 6</span>
                    <span class="tab-label">Tips</span>
                </div>
                <div class="tab" onclick="goToStep(7)" id="tab7">
                    <span class="tab-number">Step 7</span>
                    <span class="tab-label">History</span>
                </div>
            </div>
        </div>
        
        <!-- STEP 1: Set Budget -->
        <div id="step1" class="step-container active">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>Set Your Holiday Food Budget</h1>
                        <p>Set the maximum amount your household plans to spend on food during the holiday. This becomes your reference for measuring overspending.</p>
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-box-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="info-box-text">
                        Lechon is counted as one of your holiday food expenses. Enter the total food budget for the whole household for the holiday period.
                    </div>
                </div>

                <form id="budgetForm">
                    <div class="form-group">
                        <label class="form-label">Planned Food Budget (₱)</label>
                        <div class="form-input-icon">
                            <i class="fas fa-peso-sign"></i>
                            <input type="number" id="plannedBudget" class="form-input" placeholder="12000" required min="1">
                        </div>
                        <div class="form-hint">Example: ₱8,000 for a Noche Buena celebration.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Number of Family Members</label>
                        <div class="form-input-icon">
                            <i class="fas fa-users"></i>
                            <input type="number" id="familyMembers" class="form-input" placeholder="3" required min="1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Holiday / Event</label>
                        <div class="form-input-icon">
                            <i class="fas fa-calendar"></i>
                            <select id="holidayEventSelect" class="form-input" onchange="handleEventChange()" required>
                                <option value="">Select holiday or event</option>
                                <option value="Birthday"> Birthday</option>
                                <option value="Fiesta"> Fiesta</option>
                                <option value="Christmas"> Christmas</option>
                                <option value="New Year"> New Year</option>
                                <option value="Other"> Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="customEventGroup" style="display: none;">
                        <label class="form-label">Specify Event</label>
                        <div class="form-input-icon">
                            <i class="fas fa-edit"></i>
                            <input type="text" id="customEventInput" class="form-input" placeholder="Enter event name">
                        </div>
                    </div>

                    <div class="budget-summary">
                        <div class="budget-summary-item">
                            <div class="budget-summary-label">Budget</div>
                            <div class="budget-summary-value" id="displayBudget">₱0</div>
                        </div>
                        <div class="budget-summary-item">
                            <div class="budget-summary-label">Family Members</div>
                            <div class="budget-summary-value" id="displayMembers">0</div>
                        </div>
                        <div class="budget-summary-item">
                            <div class="budget-summary-label">Per Member</div>
                            <div class="budget-summary-value" id="displayPerMember">₱0</div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            Continue to Food Planning
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- STEP 2: Plan Food -->
        <div id="step2" class="step-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>Plan Your Holiday Food</h1>
                        <p>Add the food items your household plans to prepare. Lechon is included as one of your food expenses. Budget: <span id="planBudgetDisplay">₱12,000</span> - <span id="planEventDisplay">Noche Buena</span></p>
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-label">
                        <span>Budget used: <strong id="planUsedPercent">73%</strong></span>
                        <span>Remaining: <strong id="planRemainingPercent" style="color: #6b7280;">27%</strong></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="planProgressFill" style="width: 73%;">73%</div>
                    </div>
                </div>

                <div class="budget-summary">
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Planned Food Budget</div>
                        <div class="budget-summary-value" id="planBudget2">₱12,000</div>
                    </div>
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Total Planned Food Cost</div>
                        <div class="budget-summary-value" id="planTotal">₱8,750</div>
                    </div>
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Remaining Budget</div>
                        <div class="budget-summary-value" style="color: #10b981;" id="planRemaining">₱3,250</div>
                    </div>
                </div>

                <div class="food-items-header">
                    <h3>Food Items</h3>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-add" id="loadMarketplaceBtn" onclick="loadMarketplacePurchases(event)" style="background: #c0392b; border-color: #c0392b; color: white; cursor: pointer; z-index: 10; position: relative;">
                            <i class="fas fa-shopping-basket"></i>
                            Load from Marketplace
                        </button>
                        <button type="button" class="btn-add" onclick="addPlannedItem()">
                            <i class="fas fa-plus"></i>
                            Add Item
                        </button>
                    </div>
                </div>

                <div id="plannedItemsList">
                    <!-- Items will be added here -->
                </div>

                <div class="totals-box">
                    <div>
                        <div class="totals-label">Total Planned Food Cost</div>
                    </div>
                    <div class="totals-value" id="planTotalBottom">₱8,750</div>
                </div>

                <div class="totals-box" style="margin-top: 1rem;">
                    <div>
                        <div class="totals-label">Remaining Budget</div>
                    </div>
                    <div class="totals-value" style="color: #10b981;" id="planRemainingBottom">₱3,250</div>
                </div>

                <p style="text-align: center; color: #6b7280; font-size: 0.875rem; margin-top: 1rem;">
                    You can continue even if your planned items don't fill the whole budget.
                </p>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="saveFoodPlan()">
                        <i class="fas fa-save"></i>
                        Save Food Plan
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                        Save & Continue to Spending
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 3: Record Actual Spending -->
        <div id="step3" class="step-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>Record Actual Holiday Spending</h1>
                        <p>Record what your household actually spent during Noche Buena. Lechon is clearly available as a food item. The total of all actual food expenses is your Holiday AFC.</p>
                    </div>
                </div>

                <div class="food-items-header">
                    <h3>Actual Food Expenses</h3>
                    <button class="btn-add" onclick="addActualItem()">
                        <i class="fas fa-plus"></i>
                        Add Expense
                    </button>
                </div>

                <div id="actualItemsList">
                    <!-- Items will be added here -->
                </div>

                <div class="afc-box">
                    <div class="afc-box-header">Holiday AFC</div>
                    <div class="afc-box-header">Actual Food Consumption / Expenditure</div>
                    <div class="afc-box-value" id="holidayAFC">₱7,000</div>
                    <div class="afc-box-note">Holiday AFC = sum of all actual holiday food expenses (including lechon).</div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                        <i class="fas fa-arrow-left"></i>
                        Back to Food Planning
                    </button>
                    <button type="button" class="btn btn-primary" onclick="calculateResults()">
                        <i class="fas fa-calculator"></i>
                        Calculate My Holiday Spending
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 4: Results -->
        <div id="step4" class="step-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>Your Holiday Spending Result</h1>
                        <p>See how your actual holiday food spending compares with your planned budget and the 10% overspending target.</p>
                    </div>
                </div>

                <div class="result-cards">
                    <div class="result-card">
                        <div class="result-card-label">
                            <i class="fas fa-clipboard-list"></i>
                            Planned Food Budget
                        </div>
                        <div class="result-card-value" id="resultBudget">₱12,000</div>
                        <div class="result-card-sub" id="resultEvent">Noche Buena</div>
                    </div>

                    <div class="result-card">
                        <div class="result-card-label">
                            <i class="fas fa-chart-line"></i>
                            Holiday AFC
                        </div>
                        <div class="result-card-value" id="resultAFC">₱7,000</div>
                        <div class="result-card-sub">Actual food expenditure (incl. lechon orders)</div>
                    </div>

                    <div class="result-card highlight" id="overspendCard">
                        <div class="result-card-label">
                            <i class="fas fa-percentage"></i>
                            Overspending
                        </div>
                        <div class="result-card-value" id="resultOverspend">41.67%</div>
                        <div class="result-card-sub">Target: within ±10%</div>
                    </div>
                </div>

                <div class="calc-box">
                    <h3>How we calculated it</h3>
                    <div class="calc-formula">
                        <strong>Overspending % = (Holiday AFC - Planned Food Budget) ÷ Planned Food Budget × 100</strong><br>
                        = (<span id="calcAFC">₱7,000</span> - <span id="calcBudget">₱12,000</span>) ÷ <span id="calcBudget2">₱12,000</span> × 100<br>
                        = <strong id="calcAmount">₱5,000</strong> ÷ <span id="calcBudget3">₱12,000</span> × 100 = <strong id="calcPercent">41.67%</strong>
                    </div>
                </div>

                <div class="budget-summary">
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Amount Over Budget</div>
                        <div class="budget-summary-value" style="color: #10b981;" id="resultOverAmount">-₱5,000</div>
                        <div class="result-card-sub">Holiday AFC - Planned Food Budget</div>
                    </div>
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Target</div>
                        <div class="budget-summary-value" style="color: #dc2626;">Below 10%</div>
                        <div class="result-card-sub">Cautious objective threshold</div>
                    </div>
                </div>

                <div class="success-box" id="resultStatusBox">
                    <div class="success-box-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="success-box-content">
                        <h3>Under Budget</h3>
                        <p>Your holiday food spending is within the 10% target. Great job!</p>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(3)">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="orderLechon()">
                        <i class="fas fa-shopping-cart"></i>
                        Order Lechon
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(5)">
                        View Breakdown
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(6)">
                        See Recommendations
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 5: Breakdown -->
        <div id="step5" class="step-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>Holiday Food Spending Breakdown</h1>
                        <p>See how your <span id="breakdownAFC">₱7,000</span> holiday food spending is distributed across items, including lechon.</p>
                    </div>
                </div>

                <div class="budget-summary">
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Holiday AFC</div>
                        <div class="budget-summary-value" id="breakdown2AFC">₱7,000</div>
                    </div>
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Planned Budget</div>
                        <div class="budget-summary-value" id="breakdown2Budget">₱12,000</div>
                    </div>
                    <div class="budget-summary-item">
                        <div class="budget-summary-label">Overspending</div>
                        <div class="budget-summary-value" style="color: #10b981;" id="breakdown2Percent">-41.67%</div>
                        <div class="result-card-sub">Within target</div>
                    </div>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; font-size: 1.1rem;">Spending by Food Item</h3>
                <div style="margin-bottom: 1.5rem;">
                    <div style="padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6;">
                        <div class="breakdown-bar">
                            <div class="breakdown-bar-fill" style="width: 42.86%; background: #dc2626;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;"><i class="fas fa-circle" style="color: #dc2626; font-size: 0.5rem;"></i> Lechon</span>
                            <span style="font-weight: 700; color: #dc2626;">₱3,000 (42.86%)</span>
                        </div>
                    </div>
                </div>

                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Food Item</th>
                            <th>Category</th>
                            <th>Actual Spending</th>
                            <th>% of AFC</th>
                        </tr>
                    </thead>
                    <tbody id="breakdownTableBody">
                        <!-- Rows will be added here -->
                    </tbody>
                </table>

                <div class="totals-box" style="background: #dc2626; color: white;">
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Holiday AFC</div>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700;" id="breakdownTotal">₱7,000</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">100%</div>
                </div>

                <!-- Status Spending Percentage -->
                <div style="margin-top: 2rem;">
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; font-weight: 700;">
                        <i class="fas fa-crown" style="color: #dc2626;"></i>
                        Status Spending Percentage
                    </h3>
                    <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                        Measures how much of your total holiday food spending went to <strong>Lechon</strong> — the high-status food category.
                    </p>
                    <p style="font-size: 0.8rem; color: #9ca3af; margin-bottom: 1.25rem;" id="statusSpendingSource">
                        —
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="result-card">
                            <div class="result-card-label">
                                <i class="fas fa-crown"></i> Total Spending
                            </div>
                            <div class="result-card-value" id="statusSpendingAmount">₱0</div>
                            <div class="result-card-sub">High-status category amount</div>
                        </div>
                        <div class="result-card highlight">
                            <div class="result-card-label">
                                <i class="fas fa-percentage"></i> Status Spending %
                            </div>
                            <div class="result-card-value" id="statusSpendingPercent">0.00%</div>
                            <div class="result-card-sub">of total holiday spending (AFC)</div>
                        </div>
                    </div>

                    <div class="calc-box">
                        <h3>How we calculated it</h3>
                        <div class="calc-formula" id="statusSpendingCalc">
                            <strong>Status Spending % = Total Lechon Spending ÷ Total Spending × 100</strong>
                        </div>
                        <div style="margin-top: 1rem; font-size: 0.8rem; color: #6b7280;">
                            <i class="fas fa-info-circle" style="color: #dc2626;"></i>
                            Lechon spending is pulled from your actual lechon orders in
                            <a href="/my-orders?orders_tab=lechon" style="color: #dc2626; font-weight: 600;">My Orders</a>.
                            If no real orders exist, manual budget entries tagged as "Lechon" are used instead.
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(4)">
                        <i class="fas fa-arrow-left"></i>
                        Back to Results
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(6)">
                        See Recommendations
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 6: Recommendations -->
        <div id="step6" class="step-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="card-title-area">
                        <h1>LechGO Recommendation</h1>
                        <p>Personalized budgeting advice based on your holiday spending result.</p>
                    </div>
                </div>

                <div class="success-box" id="recommendStatusBox">
                    <div class="success-box-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="success-box-content">
                        <h3>Great job! You are within target.</h3>
                        <p>Your holiday food spending is within the 10% target. Keep up the good budgeting!</p>
                    </div>
                </div>

                <div class="tips-box">
                    <h3>Tips to maintain your good standing</h3>
                    <ul class="tips-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Keep tracking your spending throughout the holiday to stay within target.</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Save the difference between your planned budget and actual spending for next holiday.</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Share your budget plan with family members to keep everyone aligned.</span>
                        </li>
                    </ul>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(5)">
                        <i class="fas fa-arrow-left"></i>
                        Back to Breakdown
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(7)">
                        View History
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 7: History -->
        <div id="step7" class="step-container">
            <div class="card">
                <div class="history-header">
                    <div>
                        <h1 style="margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 700;">
                            <i class="fas fa-history" style="color: #dc2626;"></i>
                            Holiday Spending History
                        </h1>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">A report of your previous holiday records. Click a record to see its spending breakdown.</p>
                    </div>
                    <button class="btn-add" onclick="goToStep(1)">
                        <i class="fas fa-plus"></i>
                        New Record
                    </button>
                </div>

                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Holiday / Event</th>
                            <th>Planned Budget</th>
                            <th>Holiday AFC</th>
                            <th>Overspending</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <!-- Rows will be added here -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="/budget-planner.js?v=16"></script>
</body>
</html>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/dashboard-layout.php';
?>
