<?php
/**
 * Lechonero Profile Page
 */

$session = new Session();
$user = $session->getUser();

// Get employee assignment details
$employeeStatus = null;
$query = "SELECT ea.*, lo.farm_name, lo.location, lo.contact_number, u.name as owner_name
          FROM employee_assignments ea
          JOIN livestock_owners lo ON ea.livestock_owner_id = lo.id
          JOIN users u ON lo.user_id = u.id
          WHERE ea.employee_user_id = ? AND ea.status = 'active'
          ORDER BY ea.assigned_at DESC
          LIMIT 1";
$stmt = $GLOBALS['conn']->prepare($query);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $employeeStatus = $result->fetch_assoc();
}
$stmt->close();

ob_start();
?>

<div style="padding: 20px;">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #721c24;">Error:</strong><br>
            <span style="color: #721c24;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #155724;">Success!</strong><br>
            <span style="color: #155724;"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Employment Status -->
    <?php if ($employeeStatus): ?>
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            My Employment Status
        </h5>
        
        <div style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                    🔥
                </div>
                <div>
                    <h6 style="margin: 0; font-size: 14px; opacity: 0.9;">Currently Working As</h6>
                    <h4 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 600;">
                        Lechonero (Roasting Expert)
                    </h4>
                </div>
            </div>
        </div>
        
        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
            <h6 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #495057;">Farm Information</h6>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Farm Name:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['farm_name']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Location:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['location']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Owner:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['owner_name']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Contact:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['contact_number'] ?? 'N/A'); ?></span>
            </div>
            
            <div>
                <strong style="color: #6c757d; font-size: 13px;">Employment Date:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo date('F d, Y', strtotime($employeeStatus['assigned_at'])); ?></span>
            </div>
        </div>
        
        <div style="background: #fff8e1; border-left: 4px solid #ff6b35; padding: 12px; border-radius: 4px;">
            <strong style="color: #e65100; font-size: 13px;">Active Employment</strong><br>
            <span style="color: #ef6c00; font-size: 13px;">You are currently employed as a Lechonero. Contact your farm owner for any concerns about your schedule and assignments.</span>
        </div>
    </div>
    <?php else: ?>
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 15px; font-size: 18px; font-weight: 600;">
            Employment Status
        </h5>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;">
            <strong>No Active Employment</strong><br>
            <span style="color: #856404;">You are not currently assigned to any piggery farm.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Profile Information -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            Profile Information
        </h5>
        
        <div style="margin-bottom: 15px;">
            <strong>Name:</strong><br>
            <?php echo htmlspecialchars($user['name']); ?>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Email:</strong><br>
            <?php echo htmlspecialchars($user['email']); ?>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Phone:</strong><br>
            <?php echo htmlspecialchars($user['phone']); ?>
        </div>
        
        <div>
            <strong>Current Role:</strong><br>
            <span style="display: inline-block; padding: 6px 14px; background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; border-radius: 16px; font-size: 14px; margin-top: 5px;">
                Lechonero
            </span>
        </div>
    </div>
</div>

<?php
$currentPage = 'profile';
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard-layout.php';
?>
