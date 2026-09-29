<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
$page_title = 'تقرير بطاقات الشراء';

// Date filters - default to all time to show all cards
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Build query - Get all purchase cards with their actual data (matching the index.php structure)
$query = "
    SELECT 
        pc.id,
        pc.card_number,
        pc.card_name,
        pc.initial_balance,
        pc.balance as current_balance,
        pc.created_at,
        pc.status,
        COUNT(DISTINCT pb.id) as transactions_count,
        COALESCE(SUM(pb.final_amount), 0) as total_used,
        pc.initial_balance as total_added,
        COALESCE(SUM(pb.final_amount), 0) as purchase_amount
    FROM purchase_cards pc
    LEFT JOIN purchase_baskets pb ON pc.id = pb.payment_source_id 
        AND pb.payment_source_type = 'purchase_card'
    WHERE 1=1
";

$params = [];

// Add date filter only if dates are provided
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND pc.created_at BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
}

$query .= " GROUP BY pc.id ORDER BY pc.created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log query and results
    error_log("=== PURCHASE CARDS REPORT DEBUG ===");
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    error_log("Cards found: " . count($cards));
    error_log("First card data: " . print_r($cards[0] ?? 'No cards', true));
    
    // Calculate totals
    $total_cards = count($cards);
    $total_current = array_sum(array_column($cards, 'current_balance'));
    $total_used = array_sum(array_column($cards, 'total_used'));
    $total_added = array_sum(array_column($cards, 'total_added'));
    $total_purchase = array_sum(array_column($cards, 'purchase_amount'));
    $total_transactions = array_sum(array_column($cards, 'transactions_count'));
    
    error_log("Totals - Cards: $total_cards, Current: $total_current, Used: $total_used, Added: $total_added, Purchase: $total_purchase, Transactions: $total_transactions");
    
} catch (PDOException $e) {
    $error = $e->getMessage();
    error_log("ERROR in purchase cards report: " . $error);
    $cards = [];
    $total_cards = $total_current = $total_used = $total_added = $total_purchase = $total_transactions = 0;
}

// Status labels
$status_labels = [
    'active' => 'نشطة',
    'inactive' => 'غير نشطة',
    'expired' => 'منتهية',
    'blocked' => 'محظورة'
];

include '../../includes/header.php';
?>

<style>
.filter-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-box {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-right: 4px solid;
}

.data-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow-x: auto !important;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    position: relative;
    width: 100%;
    display: block;
}

.data-table table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
    display: table;
    table-layout: auto;
}

.data-table th {
    background: #f3f4f6;
    padding: 1rem;
    text-align: right;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    color: #6b7280;
    white-space: nowrap;
}

.data-table tr:hover {
    background: #f9fafb;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.export-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.export-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-pdf {
    background: #ef4444;
    color: white;
}

.btn-excel {
    background: #C7A46D;
    color: white;
}

.cost-highlight {
    background: #fef3c7;
    font-weight: bold;
    color: #92400e;
}

/* Responsive styles */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-card form {
        grid-template-columns: 1fr !important;
    }
    
    .export-buttons {
        flex-direction: column;
    }
}

/* Scrollbar styling */
.data-table::-webkit-scrollbar {
    height: 10px;
}

.data-table::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.data-table::-webkit-scrollbar-thumb {
    background: #10b981;
    border-radius: 10px;
}

.data-table::-webkit-scrollbar-thumb:hover {
    background: #059669;
}

/* Scroll indicator */
.scroll-indicator {
    text-align: center;
    padding: 10px;
    background: #ecfdf5;
    color: #059669;
    font-size: 14px;
    font-weight: 600;
    border-radius: 8px;
    margin-bottom: 10px;
    display: none;
    transition: opacity 0.3s ease;
}

@media (max-width: 1024px) {
    .scroll-indicator {
        display: block;
    }
}

/* Scroll shadow effects */
.data-table.scrolled-right {
    box-shadow: inset 10px 0 10px -10px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
}

.data-table.can-scroll-more {
    box-shadow: inset -10px 0 10px -10px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
}

.data-table.scrolled-right.can-scroll-more {
    box-shadow: inset 10px 0 10px -10px rgba(0,0,0,0.15), inset -10px 0 10px -10px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
}

/* Cursor styles for drag scrolling */
.data-table {
    cursor: grab;
    user-select: none;
}

.data-table:active {
    cursor: grabbing;
}
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-teal-700 shadow-xl rounded-2xl mb-8 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-credit-card ml-3"></i>
                        تقرير بطاقات الشراء
                    </h1>
                    <p class="text-green-100 mt-2">تقرير شامل لجميع بطاقات الشراء وتكلفتها</p>
                </div>
                <a href="index.php" class="px-6 py-3 bg-white text-green-600 rounded-xl hover:bg-green-50 font-semibold transition">
                    <i class="fas fa-arrow-right ml-2"></i>
                    العودة للتقارير
                </a>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <button class="export-btn btn-pdf" onclick="exportReport('pdf')">
                <i class="fas fa-file-pdf"></i>
                تصدير PDF
            </button>
            <button class="export-btn btn-excel" onclick="exportReport('excel')">
                <i class="fas fa-file-excel"></i>
                تصدير Excel
            </button>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">من تاريخ</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">إلى تاريخ</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        <i class="fas fa-filter ml-2"></i>
                        تصفية
                    </button>
                </div>
            </form>
        </div>

        <!-- Debug Panel -->
        <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
            <h3 style="color: #92400e; font-weight: bold; margin-bottom: 0.5rem;">
                <i class="fas fa-bug"></i> Debug Information
            </h3>
            <div style="font-size: 0.875rem; color: #78350f;">
                <p><strong>Query Executed:</strong> <?php echo htmlspecialchars(substr($query, 0, 200)); ?>...</p>
                <p><strong>Date Range:</strong> <?php echo $start_date ?: 'All Time'; ?> to <?php echo $end_date ?: 'All Time'; ?></p>
                <p><strong>Cards Found:</strong> <?php echo $total_cards; ?></p>
                <p><strong>Raw Data Sample:</strong> <?php echo htmlspecialchars(json_encode(array_slice($cards, 0, 1))); ?></p>
                <?php if (isset($error)): ?>
                <p style="color: #dc2626;"><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box" style="border-right-color: #10b981;">
                <p class="text-gray-600 text-sm">إجمالي البطاقات</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_cards, 0, '', ''); ?></p>
            </div>
            <div class="stat-box" style="border-right-color: #3b82f6;">
                <p class="text-gray-600 text-sm">المبلغ المضاف</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_added, 0, '', ''); ?> ر.ي</p>
            </div>
            <div class="stat-box" style="border-right-color: #8b5cf6;">
                <p class="text-gray-600 text-sm">الرصيد الحالي</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_current, 0, '', ''); ?> ر.ي</p>
            </div>
            <div class="stat-box" style="border-right-color: #ef4444;">
                <p class="text-gray-600 text-sm">المبلغ المستخدم</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_used, 0, '', ''); ?> ر.ي</p>
            </div>
            <div class="stat-box" style="border-right-color: #f59e0b;">
                <p class="text-gray-600 text-sm">مبلغ الشراء</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_purchase, 0, '', ''); ?> ر.ي</p>
            </div>
            <div class="stat-box" style="border-right-color: #C7A46D;">
                <p class="text-gray-600 text-sm">عدد المعاملات</p>
                <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($total_transactions, 0, '', ''); ?></p>
            </div>
        </div>

        <!-- Data Table -->
        <div class="scroll-indicator">
            <i class="fas fa-arrows-alt-h"></i>
            اسحب لليمين أو اليسار لعرض جميع الأعمدة
        </div>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم البطاقة</th>
                        <th>اسم البطاقة</th>
                        <th>الرصيد الحالي</th>
                        <th>مبلغ الشراء</th>
                        <th>المبلغ المستخدم</th>
                        <th>المبلغ المضاف</th>
                        <th>عدد المعاملات</th>
                        <th>تاريخ الإنشاء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cards)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>لا توجد بيانات للعرض</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cards as $index => $card): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($card['card_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($card['card_name'] ?? '-'); ?></td>
                                <td><strong><?php echo number_format($card['current_balance'], 0, '', ''); ?> ر.ي</strong></td>
                                <td><strong style="color: #f59e0b;"><?php echo number_format($card['purchase_amount'], 0, '', ''); ?> ر.ي</strong></td>
                                <td><?php echo number_format($card['total_used'], 0, '', ''); ?> ر.ي</td>
                                <td><?php echo number_format($card['total_added'], 0, '', ''); ?> ر.ي</td>
                                <td><?php echo $card['transactions_count']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($card['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($cards)): ?>
                <tfoot>
                    <tr style="background: #f3f4f6; font-weight: bold;">
                        <td colspan="3">الإجمالي</td>
                        <td><?php echo number_format($total_current, 0, '', ''); ?> ر.ي</td>
                        <td><strong style="color: #f59e0b;"><?php echo number_format($total_purchase, 0, '', ''); ?> ر.ي</strong></td>
                        <td><?php echo number_format($total_used, 0, '', ''); ?> ر.ي</td>
                        <td><?php echo number_format($total_added, 0, '', ''); ?> ر.ي</td>
                        <td><?php echo number_format($total_transactions, 0, '', ''); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

    </div>
</div>

<script>
// Debug console output
console.log('=== PURCHASE CARDS REPORT DEBUG ===');
console.log('Total Cards:', <?php echo $total_cards; ?>);
console.log('Total Current Balance:', <?php echo $total_current; ?>);
console.log('Total Used:', <?php echo $total_used; ?>);
console.log('Total Added:', <?php echo $total_added; ?>);
console.log('Total Purchase:', <?php echo $total_purchase; ?>);
console.log('Total Transactions:', <?php echo $total_transactions; ?>);
console.log('Cards Data:', <?php echo json_encode($cards); ?>);
console.log('Start Date:', '<?php echo $start_date; ?>');
console.log('End Date:', '<?php echo $end_date; ?>');
<?php if (isset($error)): ?>
console.error('Database Error:', '<?php echo addslashes($error); ?>');
<?php endif; ?>

function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    window.location.href = 'export_report.php?type=purchase_cards&' + params.toString();
}

// Enhanced scroll functionality
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.querySelector('.data-table');
    const scrollIndicator = document.querySelector('.scroll-indicator');
    
    if (tableContainer) {
        // Check if table needs scrolling
        function checkScroll() {
            const hasScroll = tableContainer.scrollWidth > tableContainer.clientWidth;
            if (scrollIndicator) {
                scrollIndicator.style.display = hasScroll ? 'block' : 'none';
            }
        }
        
        // Initial check
        checkScroll();
        
        // Check on window resize
        window.addEventListener('resize', checkScroll);
        
        // Add scroll shadow effect
        tableContainer.addEventListener('scroll', function() {
            const scrollLeft = this.scrollLeft;
            const scrollWidth = this.scrollWidth;
            const clientWidth = this.clientWidth;
            
            // Add shadow classes based on scroll position
            if (scrollLeft > 0) {
                this.classList.add('scrolled-right');
            } else {
                this.classList.remove('scrolled-right');
            }
            
            if (scrollLeft < scrollWidth - clientWidth - 10) {
                this.classList.add('can-scroll-more');
            } else {
                this.classList.remove('can-scroll-more');
            }
            
            // Hide indicator after first scroll
            if (scrollIndicator && scrollLeft > 50) {
                scrollIndicator.style.opacity = '0';
                setTimeout(() => {
                    scrollIndicator.style.display = 'none';
                }, 300);
            }
        });
        
        // Enable touch/mouse drag scrolling
        let isDown = false;
        let startX;
        let scrollLeftStart;
        
        tableContainer.addEventListener('mousedown', (e) => {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
            isDown = true;
            tableContainer.style.cursor = 'grabbing';
            startX = e.pageX - tableContainer.offsetLeft;
            scrollLeftStart = tableContainer.scrollLeft;
        });
        
        tableContainer.addEventListener('mouseleave', () => {
            isDown = false;
            tableContainer.style.cursor = 'grab';
        });
        
        tableContainer.addEventListener('mouseup', () => {
            isDown = false;
            tableContainer.style.cursor = 'grab';
        });
        
        tableContainer.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - tableContainer.offsetLeft;
            const walk = (x - startX) * 2;
            tableContainer.scrollLeft = scrollLeftStart - walk;
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
