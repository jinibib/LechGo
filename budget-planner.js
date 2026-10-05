/**
 * Budget Planner JavaScript - Holiday Food Spending Tool
 * Multi-step budget planning with overspending calculation
 */

// Global state
let budgetData = {
    id: null,
    plannedBudget: 0,
    familyMembers: 0,
    holidayEvent: '',
    plannedItems: [],
    actualItems: [],
    savedRecords: [],
    // Total lechon spending from lechon_orders table (real orders, not manual entries)
    totalLechonSpending: 0,
    lechonOrderCount: 0
};

// Event emojis mapping
const eventEmojis = {
    'Birthday': '🎂',
    'Fiesta': '🎉',
    'Christmas': '🎄',
    'New Year': '🎊',
    'Noche Buena': '🎄',
    'Other': '📅'
};

// Load data from database (replaced localStorage)
async function loadBudgetData() {
    console.log('=== LOADING BUDGET DATA FROM DATABASE ===');
    try {
        const response = await fetch('/api/budget-planner/records');
        const data = await response.json();
        
        console.log('API Response:', data);
        
        if (data.success && data.records) {
            budgetData.savedRecords = data.records;
            console.log('Total records found:', data.records.length);
            
            // Load the most recent record as current working data
            if (data.records.length > 0) {
                const latestRecord = data.records[0]; // Records are ordered by created_at DESC
                
                console.log('Loading latest record:', latestRecord);
                
                // Load this record into budgetData
                budgetData.id = latestRecord.id;
                budgetData.plannedBudget = parseFloat(latestRecord.planned_budget);
                budgetData.familyMembers = parseInt(latestRecord.family_members);
                budgetData.holidayEvent = latestRecord.holiday_event;
                budgetData.plannedItems = latestRecord.planned_items || [];
                budgetData.actualItems = latestRecord.actual_items || [];
                
                console.log('✅ Loaded budget data:', budgetData);
                console.log('   - Planned Budget:', budgetData.plannedBudget);
                console.log('   - Family Members:', budgetData.familyMembers);
                console.log('   - Holiday Event:', budgetData.holidayEvent);
                console.log('   - Planned Items:', budgetData.plannedItems);
                console.log('   - Actual Items:', budgetData.actualItems);
                
                // Update all displays
                console.log('Updating all displays...');
                updateBudgetSummary();
                updateFoodPlanningDisplay();
                updateActualSpendingDisplay();
                updateResultsDisplay();
                updateBreakdownDisplay();
                updateRecommendationsDisplay();
                console.log('✅ All displays updated');
            } else {
                console.log('⚠️ No records found in database');
            }
            
            loadHistoryTable();
        } else {
            console.error('❌ Failed to load records:', data);
        }
    } catch (error) {
        console.error('❌ Error loading budget data:', error);
    }
}

// Load total lechon spending from real lechon_orders table
async function loadLechonSpending() {
    try {
        const response = await fetch('/api/budget-planner/lechon-spending');
        const data = await response.json();
        if (data.success) {
            budgetData.totalLechonSpending = data.total_lechon_spending || 0;
            budgetData.lechonOrderCount    = data.order_count || 0;
            console.log('✅ Lechon spending loaded:', budgetData.totalLechonSpending,
                        '(', budgetData.lechonOrderCount, 'orders)');
        }
    } catch (error) {
        console.error('❌ Error loading lechon spending:', error);
        // Keep defaults (0) on error — formula still works with fallback
    }
}

// Save data to database (replaced localStorage)
async function saveBudgetData() {
    try {
        const saveData = {
            id: budgetData.id,
            holiday_event: budgetData.holidayEvent,
            planned_budget: budgetData.plannedBudget,
            family_members: budgetData.familyMembers,
            planned_items: budgetData.plannedItems,
            actual_items: budgetData.actualItems,
            status: budgetData.actualItems.length > 0 ? 'completed' : 'planning'
        };

        const response = await fetch('/api/budget-planner/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saveData)
        });

        const data = await response.json();
        
        if (data.success) {
            if (!budgetData.id && data.id) {
                budgetData.id = data.id;
            }
            return true;
        } else {
            console.error('Failed to save:', data.message);
            return false;
        }
    } catch (error) {
        console.error('Error saving budget data:', error);
        return false;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Budget Planner initialized');
    // Load both in parallel, then refresh breakdown once lechon data is ready
    await Promise.all([loadBudgetData(), loadLechonSpending()]);
    updateBreakdownDisplay(); // re-render with real lechon total now available
    setupBudgetForm();
    updateBudgetSummary();
});

// Handle event dropdown change
function handleEventChange() {
    const select = document.getElementById('holidayEventSelect');
    const customGroup = document.getElementById('customEventGroup');
    const customInput = document.getElementById('customEventInput');
    
    if (!select) return;
    
    if (select.value === 'Other') {
        customGroup.style.display = 'block';
        customInput.required = true;
    } else {
        customGroup.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

// Get the holiday event value (from dropdown or custom input)
function getHolidayEventValue() {
    const select = document.getElementById('holidayEventSelect');
    const customInput = document.getElementById('customEventInput');
    
    if (!select) {
        console.error('Holiday event select not found!');
        return '';
    }
    
    // Get selected value - try multiple ways
    let selectedValue = select.value;
    if (!selectedValue) {
        // Try getting from selectedIndex
        const selectedOption = select.options[select.selectedIndex];
        selectedValue = selectedOption ? selectedOption.value : '';
    }
    
    console.log('Select value:', selectedValue);
    console.log('Select selectedIndex:', select.selectedIndex);
    
    if (selectedValue === 'Other') {
        const customValue = customInput ? customInput.value.trim() : '';
        console.log('Custom event value:', customValue);
        return customValue;
    }
    
    console.log('Selected event:', selectedValue);
    return selectedValue;
}

// Setup Budget Form (Step 1)
function setupBudgetForm() {
    const form = document.getElementById('budgetForm');
    const budgetInput = document.getElementById('plannedBudget');
    const membersInput = document.getElementById('familyMembers');
    
    if (!form || !budgetInput || !membersInput) {
        console.error('Form elements not found');
        return;
    }
    
    // Update summary on input - use both 'input' and 'change' events
    budgetInput.addEventListener('input', updateBudgetSummary);
    budgetInput.addEventListener('change', updateBudgetSummary);
    budgetInput.addEventListener('keyup', updateBudgetSummary);
    
    membersInput.addEventListener('input', updateBudgetSummary);
    membersInput.addEventListener('change', updateBudgetSummary);
    membersInput.addEventListener('keyup', updateBudgetSummary);
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        const budget = parseFloat(budgetInput.value);
        const members = parseInt(membersInput.value);
        const event = getHolidayEventValue();
        
        console.log('Budget:', budget, 'Members:', members, 'Event:', event);
        
        // Validate budget
        if (isNaN(budget) || budget <= 0) {
            alert('Please enter a valid budget amount');
            budgetInput.focus();
            return;
        }
        
        // Validate members
        if (isNaN(members) || members <= 0) {
            alert('Please enter number of family members');
            membersInput.focus();
            return;
        }
        
        // Validate event - IMPROVED
        const eventSelect = document.getElementById('holidayEventSelect');
        if (!eventSelect || !eventSelect.value || eventSelect.value === '') {
            alert('Please select a holiday or event from the dropdown');
            eventSelect.focus();
            return;
        }
        
        // If "Other" is selected, check custom input
        if (eventSelect.value === 'Other') {
            const customInput = document.getElementById('customEventInput');
            if (!customInput || !customInput.value || customInput.value.trim() === '') {
                alert('Please enter a custom event name');
                if (customInput) customInput.focus();
                return;
            }
        }
        
        // Final event value check
        if (!event || event.trim() === '') {
            alert('Event validation failed - please select or enter an event');
            return;
        }
        
        console.log('Validation passed, saving...');
        
        // Reset for new budget
        budgetData.id = null;
        budgetData.plannedBudget = budget;
        budgetData.familyMembers = members;
        budgetData.holidayEvent = event;
        budgetData.plannedItems = [];
        budgetData.actualItems = [];
        
        const saved = await saveBudgetData();
        if (saved) {
            console.log('Budget saved, moving to step 2');
            goToStep(2);
        } else {
            alert('Failed to save budget. Please try again.');
        }
    });
    
    console.log('Budget form setup complete');
}

// Update Budget Summary (Step 1)
function updateBudgetSummary() {
    const budgetInput = document.getElementById('plannedBudget');
    const membersInput = document.getElementById('familyMembers');
    
    if (!budgetInput || !membersInput) {
        console.error('Budget or members input not found');
        return;
    }
    
    const budget = parseFloat(budgetInput.value) || 0;
    const members = parseInt(membersInput.value) || 0;
    const perMember = members > 0 ? budget / members : 0;
    
    console.log('Updating summary - Budget:', budget, 'Members:', members, 'Per Member:', perMember);
    
    const displayBudget = document.getElementById('displayBudget');
    const displayMembers = document.getElementById('displayMembers');
    const displayPerMember = document.getElementById('displayPerMember');
    
    if (displayBudget) {
        displayBudget.textContent = formatCurrency(budget);
    } else {
        console.error('displayBudget element not found');
    }
    
    if (displayMembers) {
        displayMembers.textContent = members;
    } else {
        console.error('displayMembers element not found');
    }
    
    if (displayPerMember) {
        displayPerMember.textContent = formatCurrency(perMember);
    } else {
        console.error('displayPerMember element not found');
    }
}

// Go to Step
function goToStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.step-container').forEach(step => {
        step.classList.remove('active');
    });
    
    // Show target step
    document.getElementById('step' + stepNumber).classList.add('active');
    
    // Update tab highlighting
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    const activeTab = document.getElementById('tab' + stepNumber);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // STEP 3: Auto-populate actual items from planned items if empty
    if (stepNumber === 3 && budgetData.actualItems.length === 0 && budgetData.plannedItems.length > 0) {
        console.log('📋 Auto-populating actual items from planned items...');
        budgetData.actualItems = budgetData.plannedItems.map(item => ({
            name: item.name,
            category: item.category,
            amount: item.price * item.qty // Use planned cost as starting point
        }));
        console.log('✅ Created actual items:', budgetData.actualItems);
        saveBudgetData(); // Save the auto-populated items
    }
    
    // Update displays based on step
    if (stepNumber === 2) {
        updateFoodPlanningDisplay();
        // NEW: Auto-load marketplace purchases on first visit to Step 2
        // Check if we haven't loaded yet (to avoid re-loading on every visit)
        if (!window.marketplacePurchasesLoaded) {
            loadMarketplacePurchases().then(() => {
                window.marketplacePurchasesLoaded = true;
            });
        }
    } else if (stepNumber === 3) {
        updateActualSpendingDisplay();
    } else if (stepNumber === 4) {
        updateResultsDisplay();
    } else if (stepNumber === 5) {
        updateBreakdownDisplay();
    } else if (stepNumber === 6) {
        updateRecommendationsDisplay();
    } else if (stepNumber === 7) {
        loadHistoryTable();
    }
    
    // Scroll to top
    window.scrollTo(0, 0);
}

// STEP 2: Food Planning

// ========== NEW: FETCH MARKETPLACE PURCHASES ==========
async function loadMarketplacePurchases(evt) {
    console.log('🛒 Loading marketplace purchases...');
    
    // Prevent any default behavior
    if (evt) {
        evt.preventDefault();
        evt.stopPropagation();
    }
    
    // Show loading indicator
    const loadBtn = evt?.target || document.getElementById('loadMarketplaceBtn');
    const originalBtnText = loadBtn?.innerHTML;
    if (loadBtn) {
        loadBtn.disabled = true;
        loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    }
    
    try {
        const response = await fetch('/customer/get-marketplace-purchases');
        
        if (!response.ok) {
            console.error('❌ HTTP error:', response.status);
            alert('Failed to load marketplace purchases. Please try again.');
            return;
        }
        
        const data = await response.json();
        console.log('📦 API Response:', data);
        
        if (data.success && data.purchases && Array.isArray(data.purchases)) {
            console.log(`✅ Found ${data.purchases.length} marketplace purchase(s)`);
            
            if (data.purchases.length === 0) {
                alert('No marketplace orders found. Place an order in the Marketplace first.');
                return;
            }
            
            let addedCount = 0;
            
            // Add each purchase to plannedItems if not already added
            data.purchases.forEach(purchase => {
                // Check if this order is already in plannedItems (deduplication by order_number)
                const exists = budgetData.plannedItems.find(
                    item => item.marketplace_order === purchase.order_number
                );
                
                if (!exists) {
                    const isPaid = purchase.payment_status === 'paid';
                    console.log(`  ➕ Adding: ${purchase.name} (${purchase.order_number}) - ₱${purchase.price} [${purchase.payment_status}]`);
                    
                    budgetData.plannedItems.push({
                        name: purchase.name,
                        category: purchase.category,
                        price: purchase.price,
                        qty: purchase.qty,
                        marketplace_order: purchase.order_number, // Track source to prevent duplicates
                        marketplace_payment_status: purchase.payment_status,
                        marketplace_order_status: purchase.order_status,
                        isFromMarketplace: true
                    });
                    
                    addedCount++;
                } else {
                    console.log(`  ⏭️ Skipping duplicate: ${purchase.name} (${purchase.order_number})`);
                }
            });
            
            if (addedCount > 0) {
                console.log(`✅ Auto-populated ${addedCount} item(s) from marketplace`);
                await saveBudgetData(); // Save the updated items
                updateFoodPlanningDisplay(); // Refresh the display
                alert(`✅ Added ${addedCount} marketplace order(s) to your budget plan.\n\nNote: Unpaid orders are already included so you can plan your budget in advance.`);
            } else {
                alert('ℹ️ All your marketplace orders are already in your planner.');
            }
        } else {
            console.log('ℹ️ No marketplace purchases found');
            console.log('   Response:', data);
            alert('No marketplace orders found. Place an order in the Marketplace first.');
        }
    } catch (error) {
        console.error('❌ Error loading marketplace purchases:', error);
        alert('Error loading marketplace purchases. Please check console for details.');
    } finally {
        // Restore button
        if (loadBtn && originalBtnText) {
            loadBtn.disabled = false;
            loadBtn.innerHTML = originalBtnText;
        }
    }
}
// ========== END NEW CODE ==========

function updateFoodPlanningDisplay() {
    // Update header
    document.getElementById('planBudgetDisplay').textContent = formatCurrency(budgetData.plannedBudget);
    document.getElementById('planEventDisplay').textContent = budgetData.holidayEvent;
    document.getElementById('planBudget2').textContent = formatCurrency(budgetData.plannedBudget);
    
    // Calculate totals
    const totalPlanned = budgetData.plannedItems.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const remaining = budgetData.plannedBudget - totalPlanned;
    const usedPercent = budgetData.plannedBudget > 0 ? Math.round((totalPlanned / budgetData.plannedBudget * 100)) : 0;
    const remainingPercent = 100 - usedPercent; // This ensures they always add up to 100%
    
    // Update summary
    document.getElementById('planTotal').textContent = formatCurrency(totalPlanned);
    document.getElementById('planRemaining').textContent = formatCurrency(remaining);
    document.getElementById('planTotalBottom').textContent = formatCurrency(totalPlanned);
    document.getElementById('planRemainingBottom').textContent = formatCurrency(remaining);
    
    // Update progress with both used and remaining percentages
    document.getElementById('planUsedPercent').textContent = usedPercent + '%';
    document.getElementById('planRemainingPercent').textContent = remainingPercent + '%';
    document.getElementById('planProgressFill').style.width = usedPercent + '%';
    document.getElementById('planProgressFill').textContent = usedPercent + '%';
    
    // Render items
    renderPlannedItems();
}

function renderPlannedItems() {
    const container = document.getElementById('plannedItemsList');
    
    if (budgetData.plannedItems.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #9ca3af; padding: 2rem;">No items added yet. Click "Add Item" to start planning.</p>';
        return;
    }
    
    let html = '';
    
    budgetData.plannedItems.forEach((item, index) => {
        // NEW: Visual indicator for marketplace items
        const paymentLabel = item.marketplace_payment_status === 'paid'
            ? `<span style="font-size:0.7rem;color:#fff;background:#059669;padding:2px 6px;border-radius:4px;margin-left:4px;">Paid</span>`
            : item.marketplace_payment_status
                ? `<span style="font-size:0.7rem;color:#92400e;background:#fef3c7;padding:2px 6px;border-radius:4px;margin-left:4px;">Payment Pending</span>`
                : '';
        const marketplaceBadge = item.isFromMarketplace 
            ? `<span style="font-size: 0.7rem; color: #059669; background: #d1fae5; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">From Marketplace</span>${paymentLabel}` 
            : '';
        
        html += `
            <div class="food-item-row">
                <div>
                    <div class="food-item-label">Food name ${marketplaceBadge}</div>
                    <input type="text" class="form-input" value="${item.name}" 
                           onchange="updatePlannedItem(${index}, 'name', this.value)">
                </div>
                <div>
                    <div class="food-item-label">Category</div>
                    <select class="form-input" onchange="updatePlannedItem(${index}, 'category', this.value)">
                        <option value="Lechon" ${item.category === 'Lechon' ? 'selected' : ''}>Lechon</option>
                        <option value="Meat" ${item.category === 'Meat' ? 'selected' : ''}>Meat</option>
                        <option value="Dessert" ${item.category === 'Dessert' ? 'selected' : ''}>Dessert</option>
                        <option value="Vegetables" ${item.category === 'Vegetables' ? 'selected' : ''}>Vegetables</option>
                        <option value="Drinks" ${item.category === 'Drinks' ? 'selected' : ''}>Drinks</option>
                        <option value="Other" ${item.category === 'Other' ? 'selected' : ''}>Other</option>
                    </select>
                </div>
                <div>
                    <div class="food-item-label">Planned ₱</div>
                    <input type="number" class="form-input" value="${item.price}" min="0"
                           onchange="updatePlannedItem(${index}, 'price', parseFloat(this.value))">
                </div>
                <div>
                    <div class="food-item-label">Qty</div>
                    <input type="number" class="form-input" value="${item.qty}" min="1"
                           onchange="updatePlannedItem(${index}, 'qty', parseInt(this.value))">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button class="btn-icon" onclick="removePlannedItem(${index})" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function addPlannedItem() {
    budgetData.plannedItems.push({
        name: '',
        category: 'Other',
        price: 0,
        qty: 1
    });
    renderPlannedItems();
}

function updatePlannedItem(index, field, value) {
    budgetData.plannedItems[index][field] = value;
    saveBudgetData();
    updateFoodPlanningDisplay();
}

function removePlannedItem(index) {
    if (confirm('Remove this item?')) {
        budgetData.plannedItems.splice(index, 1);
        saveBudgetData();
        updateFoodPlanningDisplay();
    }
}

async function saveFoodPlan() {
    const saved = await saveBudgetData();
    if (saved) {
        alert('Food plan saved!');
    } else {
        alert('Failed to save food plan. Please try again.');
    }
}

// STEP 3: Actual Spending

function updateActualSpendingDisplay() {
    renderActualItems();
    updateHolidayAFC();
}

function renderActualItems() {
    const container = document.getElementById('actualItemsList');
    
    if (budgetData.actualItems.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #9ca3af; padding: 2rem;">No expenses recorded yet. Click "Add Expense" to start tracking.</p>';
        return;
    }
    
    let html = '';
    
    budgetData.actualItems.forEach((item, index) => {
        html += `
            <div class="food-item-row">
                <div>
                    <div class="food-item-label">Food item</div>
                    <input type="text" class="form-input" value="${item.name}" 
                           onchange="updateActualItem(${index}, 'name', this.value)">
                </div>
                <div>
                    <div class="food-item-label">Category</div>
                    <select class="form-input" onchange="updateActualItem(${index}, 'category', this.value)">
                        <option value="Lechon" ${item.category === 'Lechon' ? 'selected' : ''}> Lechon</option>
                        <option value="Meat" ${item.category === 'Meat' ? 'selected' : ''}>Meat</option>
                        <option value="Dessert" ${item.category === 'Dessert' ? 'selected' : ''}>Dessert</option>
                        <option value="Vegetables" ${item.category === 'Vegetables' ? 'selected' : ''}>Vegetables</option>
                        <option value="Dairy" ${item.category === 'Dairy' ? 'selected' : ''}>Dairy</option>
                        <option value="Drinks" ${item.category === 'Drinks' ? 'selected' : ''}>Drinks</option>
                        <option value="Other" ${item.category === 'Other' ? 'selected' : ''}>Other</option>
                    </select>
                </div>
                <div>
                    <div class="food-item-label">Actual ₱</div>
                    <input type="number" class="form-input" value="${item.amount}" min="0"
                           onchange="updateActualItem(${index}, 'amount', parseFloat(this.value))">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button class="btn-icon" onclick="removeActualItem(${index})" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function addActualItem() {
    budgetData.actualItems.push({
        name: '',
        category: 'Other',
        amount: 0
    });
    renderActualItems();
}

function updateActualItem(index, field, value) {
    budgetData.actualItems[index][field] = value;
    saveBudgetData();
    updateHolidayAFC();
}

function removeActualItem(index) {
    if (confirm('Remove this expense?')) {
        budgetData.actualItems.splice(index, 1);
        saveBudgetData();
        updateActualSpendingDisplay();
    }
}

function updateHolidayAFC() {
    const total = budgetData.actualItems.reduce((sum, item) => sum + item.amount, 0);
    document.getElementById('holidayAFC').textContent = formatCurrency(total);
}

// STEP 4: Results

function calculateResults() {
    if (budgetData.actualItems.length === 0) {
        alert('Please add at least one actual expense before calculating results.');
        return;
    }
    
    goToStep(4);
}

function updateResultsDisplay() {
    const plannedBudget = budgetData.plannedBudget;
    const holidayAFC = budgetData.actualItems.reduce((sum, item) => sum + item.amount, 0);
    const difference = holidayAFC - plannedBudget; // AFC - Budget (positive = overspent)
    const overspendingPercent = plannedBudget > 0 ? (difference / plannedBudget * 100) : 0;
    
    // Green if underspent (negative). Red if any overspending (positive)
    const isGoodBudget = overspendingPercent <= 0;
    
    // Update cards
    document.getElementById('resultBudget').textContent = formatCurrency(plannedBudget);
    document.getElementById('resultEvent').textContent = budgetData.holidayEvent;
    document.getElementById('resultAFC').textContent = formatCurrency(holidayAFC);
    document.getElementById('resultOverspend').textContent = overspendingPercent.toFixed(2) + '%';
    
    // Update overspending card styling
    const overspendCard = document.getElementById('overspendCard');
    if (overspendCard) {
        if (isGoodBudget) {
            // Within ±10% range - GREEN
            overspendCard.className = 'result-card highlight';
        } else {
            // Outside ±10% range - RED
            overspendCard.className = 'result-card';
            overspendCard.style.background = '#fee2e2';
            overspendCard.style.borderColor = '#dc2626';
        }
    }
    
    // Update calculation
    document.getElementById('calcAFC').textContent = formatCurrency(holidayAFC);
    document.getElementById('calcBudget').textContent = formatCurrency(plannedBudget);
    document.getElementById('calcBudget2').textContent = formatCurrency(plannedBudget);
    document.getElementById('calcBudget3').textContent = formatCurrency(plannedBudget);
    document.getElementById('calcAmount').textContent = (difference >= 0 ? '' : '-') + formatCurrency(Math.abs(difference));
    document.getElementById('calcPercent').textContent = overspendingPercent.toFixed(2) + '%';
    
    // Update summary
    document.getElementById('resultOverAmount').textContent = (difference >= 0 ? '' : '-') + formatCurrency(Math.abs(difference));
    
    // Update status box
    const statusBox = document.getElementById('resultStatusBox');
    if (isGoodBudget) {
        statusBox.className = 'success-box';
        statusBox.innerHTML = `
            <div class="success-box-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-box-content">
                <h3>Within Budget Target</h3>
                <p>Your spending is within the ±10% acceptable range. ${overspendingPercent < 0 ? 'You underspent by ' + Math.abs(overspendingPercent).toFixed(1) + '%.' : 'You overspent by ' + overspendingPercent.toFixed(1) + '%.'}</p>
            </div>
        `;
    } else {
        statusBox.className = 'success-box';
        statusBox.style.background = '#fee2e2';
        statusBox.style.borderColor = '#dc2626';
        const isOverspent = overspendingPercent > 10;
        statusBox.innerHTML = `
            <div class="success-box-icon" style="background: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="success-box-content">
                <h3 style="color: #991b1b;">${isOverspent ? 'Overspending Alert!' : 'Significant Underspending'}</h3>
                <p style="color: #b91c1c;">${isOverspent ? 'You overspent by ' + overspendingPercent.toFixed(1) + '%. This exceeds the 10% acceptable variance.' : 'You underspent by ' + Math.abs(overspendingPercent).toFixed(1) + '%. This may indicate over-planning.'}</p>
            </div>
        `;
    }
}

// STEP 5: Breakdown

function updateBreakdownDisplay() {
    const plannedBudget = budgetData.plannedBudget;
    const holidayAFC = budgetData.actualItems.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
    const difference = holidayAFC - plannedBudget;
    const overspendingPercent = plannedBudget > 0 ? (difference / plannedBudget * 100) : 0;
    
    // Green if underspent (negative). Red if any overspending (positive)
    const isGoodBudget = overspendingPercent <= 0;
    const color = isGoodBudget ? '#10b981' : '#dc2626';
    
    // Update headers
    document.getElementById('breakdownAFC').textContent = formatCurrency(holidayAFC);
    document.getElementById('breakdown2AFC').textContent = formatCurrency(holidayAFC);
    document.getElementById('breakdown2Budget').textContent = formatCurrency(plannedBudget);
    
    const percentElement = document.getElementById('breakdown2Percent');
    if (percentElement) {
        percentElement.textContent = overspendingPercent.toFixed(2) + '%';
        percentElement.style.color = color;
    }
    
    document.getElementById('breakdownTotal').textContent = formatCurrency(holidayAFC);

    // --- Status Spending Percentage ---
    // Uses real lechon order total from lechon_orders table (fetched via API)
    // Falls back to manual budget-planner entries tagged as 'Lechon' if no real orders exist
    const realLechonTotal = budgetData.totalLechonSpending || 0;
    const manualLechonTotal = budgetData.actualItems
        .filter(item => item.category === 'Lechon')
        .reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

    console.log('=== STATUS SPENDING DEBUG ===');
    console.log('realLechonTotal (from DB orders):', realLechonTotal);
    console.log('manualLechonTotal (from actualItems):', manualLechonTotal);
    console.log('holidayAFC (total actual spending):', holidayAFC);
    console.log('actualItems count:', budgetData.actualItems.length);
    console.log('lechonOrderCount:', budgetData.lechonOrderCount);
    console.log('actualItems detail:', JSON.stringify(budgetData.actualItems));
    // Prefer real order data; use manual entries as fallback
    const highStatusTotal = realLechonTotal > 0 ? realLechonTotal : manualLechonTotal;
    const dataSource = realLechonTotal > 0
        ? `From ${budgetData.lechonOrderCount} actual lechon order${budgetData.lechonOrderCount !== 1 ? 's' : ''} (My Orders)`
        : 'From Budget Planner manual entries';

    const statusSpendingPercent = holidayAFC > 0 ? (highStatusTotal / holidayAFC * 100) : 0;

    const statusSpendEl = document.getElementById('statusSpendingPercent');
    const statusSpendAmtEl = document.getElementById('statusSpendingAmount');
    const statusSpendCalcEl = document.getElementById('statusSpendingCalc');
    const statusSourceEl = document.getElementById('statusSpendingSource');
    if (statusSpendEl) statusSpendEl.textContent = statusSpendingPercent.toFixed(2) + '%';
    if (statusSpendAmtEl) statusSpendAmtEl.textContent = formatCurrency(highStatusTotal);
    if (statusSourceEl) statusSourceEl.textContent = dataSource;
    if (statusSpendCalcEl) {
        statusSpendCalcEl.innerHTML =
            `<strong>Status Spending % = Total Lechon Spending ÷ Total Spending × 100</strong><br>` +
            `= ${formatCurrency(highStatusTotal)} ÷ ${formatCurrency(holidayAFC)} × 100` +
            ` = <strong>${statusSpendingPercent.toFixed(2)}%</strong>`;
    }

    // Render table — tag Lechon items as high status
    const tbody = document.getElementById('breakdownTableBody');
    let html = '';

    budgetData.actualItems.forEach(item => {
        const percent = holidayAFC > 0 ? (item.amount / holidayAFC * 100) : 0;
        const isHighStatus = item.category === 'Lechon';
        html += `
            <tr>
                <td><strong>${item.name}</strong></td>
                <td>
                    ${item.category}
                    ${isHighStatus ? '<span style="background:#fee2e2;color:#dc2626;font-size:0.7rem;padding:2px 6px;border-radius:10px;margin-left:4px;font-weight:600;">High Status</span>' : ''}
                </td>
                <td><strong style="color: #dc2626;">${formatCurrency(item.amount)}</strong></td>
                <td>${percent.toFixed(2)}%</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// STEP 6: Recommendations

function updateRecommendationsDisplay() {
    const plannedBudget = budgetData.plannedBudget;
    const holidayAFC = budgetData.actualItems.reduce((sum, item) => sum + item.amount, 0);
    const difference = holidayAFC - plannedBudget;
    const overspendingPercent = plannedBudget > 0 ? (difference / plannedBudget * 100) : 0;
    
    const statusBox = document.getElementById('recommendStatusBox');
    
    // Green if underspent (negative). Red if any overspending (positive)
    if (overspendingPercent <= 0) {
        statusBox.className = 'success-box';
        const message = overspendingPercent < 0 
            ? `You underspent by ${Math.abs(overspendingPercent).toFixed(1)}%. Great job staying under budget!`
            : `You overspent by ${overspendingPercent.toFixed(1)}%, which is within the acceptable 10% range.`;
        
        statusBox.innerHTML = `
            <div class="success-box-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-box-content">
                <h3>Great job! You are within target.</h3>
                <p>${message} Keep up the good budgeting!</p>
            </div>
        `;
    } else {
        statusBox.className = 'success-box';
        statusBox.style.background = '#fee2e2';
        statusBox.style.borderColor = '#dc2626';
        statusBox.innerHTML = `
            <div class="success-box-icon" style="background: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="success-box-content">
                <h3 style="color: #991b1b;">You exceeded the 10% target</h3>
                <p style="color: #b91c1c;">Your spending was ${overspendingPercent.toFixed(1)}% over budget. Review your expenses and adjust for next time.</p>
            </div>
        `;
    }
}

// STEP 7: History

function loadHistoryTable() {
    console.log('=== LOADING HISTORY TABLE ===');
    console.log('savedRecords:', budgetData.savedRecords);
    
    const tbody = document.getElementById('historyTableBody');
    
    if (!tbody) {
        console.error('❌ historyTableBody element not found!');
        return;
    }
    
    if (!budgetData.savedRecords || budgetData.savedRecords.length === 0) {
        console.log('⚠️ No saved records to display');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #9ca3af; padding: 3rem;">No previous records. Create your first holiday budget record!</td></tr>';
        return;
    }
    
    console.log(`✅ Loading ${budgetData.savedRecords.length} records into table`);
    let html = '';
    
    budgetData.savedRecords.forEach((record, index) => {
        console.log(`  Record ${index + 1}:`, record);
        const overspend = parseFloat(record.overspending_percent);
        
        // Green if underspent (negative). Red if any overspending (positive)
        const isGoodBudget = overspend <= 0;
        
        const statusClass = isGoodBudget ? 'success' : 'danger';
        const statusText = isGoodBudget ? 'Within Target' : 'Exceeded Target';
        const dateCreated = new Date(record.created_at).toLocaleDateString();
        
        // Get emoji for event or default to 
        const emoji = eventEmojis[record.holiday_event] || '';
        
        html += `
            <tr>
                <td><strong>${emoji} ${record.holiday_event}</strong></td>
                <td>${formatCurrency(parseFloat(record.planned_budget))}</td>
                <td>${formatCurrency(parseFloat(record.holiday_afc))}</td>
                <td style="color: ${isGoodBudget ? '#10b981' : '#dc2626'}; font-weight: 600;">
                    ${overspend.toFixed(2)}%
                </td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${dateCreated}</td>
                <td>
                    <button class="btn-icon" onclick="viewRecord(${record.id})" title="View">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button class="btn-icon" onclick="deleteRecord(${record.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    console.log('✅ History table loaded successfully');
}

async function saveToHistory() {
    // Data is already saved in database via saveBudgetData
    // Just mark status as completed
    budgetData.actualItems.length > 0; // Has actual items means completed
    await saveBudgetData();
    
    // Reload records
    await loadBudgetData();
}

async function viewRecord(recordId) {
    try {
        const response = await fetch(`/api/budget-planner/records`);
        const data = await response.json();
        
        if (data.success) {
            const record = data.records.find(r => r.id == recordId);
            if (record) {
                // Load record data
                budgetData.id = record.id;
                budgetData.plannedBudget = parseFloat(record.planned_budget);
                budgetData.familyMembers = parseInt(record.family_members);
                budgetData.holidayEvent = record.holiday_event;
                budgetData.plannedItems = record.planned_items || [];
                budgetData.actualItems = record.actual_items || [];
                
                // Set the form values for Step 1 if user goes back
                document.getElementById('plannedBudget').value = budgetData.plannedBudget;
                document.getElementById('familyMembers').value = budgetData.familyMembers;
                
                // Set dropdown value
                const eventSelect = document.getElementById('holidayEventSelect');
                const predefinedEvents = ['Birthday', 'Fiesta', 'Christmas', 'New Year'];
                
                if (predefinedEvents.includes(budgetData.holidayEvent)) {
                    eventSelect.value = budgetData.holidayEvent;
                } else {
                    eventSelect.value = 'Other';
                    document.getElementById('customEventInput').value = budgetData.holidayEvent;
                    handleEventChange();
                }
                
                // Go to results
                goToStep(4);
            }
        }
    } catch (error) {
        console.error('Error loading record:', error);
        alert('Failed to load record');
    }
}

async function deleteRecord(recordId) {
    if (confirm('Delete this record?')) {
        try {
            const response = await fetch('/api/budget-planner/records', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: recordId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await loadBudgetData();
            } else {
                alert('Failed to delete record');
            }
        } catch (error) {
            console.error('Error deleting record:', error);
            alert('Failed to delete record');
        }
    }
}

// Helper: Format Currency
function formatCurrency(amount) {
    return '₱' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Order Lechon (redirect to browse lechon page)
async function orderLechon() {
    // Save current record to history first
    await saveToHistory();
    
    // Redirect to browse lechon page
    window.location.href = '/customer/browse-lechon';
}
