<?php
/**
 * Market Intelligence Page - Complete & Working
 * Pig & Lechon market data from MarketAux with pinning and notes
 */
?>

<style>
    * { box-sizing: border-box; }
    
    .mi-container { max-width: 1400px; margin: 0 auto; padding: 0 1rem; }

    /* TABS */
    .mi-tabs {
        display: flex;
        gap: 0;
        border-bottom: 3px solid #F0F0F0;
        margin-bottom: 2rem;
        overflow-x: auto;
    }

    .mi-tab {
        flex: 1;
        min-width: 130px;
        padding: 1.2rem 1.5rem;
        text-align: center;
        cursor: pointer;
        border: none;
        background: transparent;
        color: #999;
        font-weight: 600;
        font-size: 0.95rem;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .mi-tab:hover {
        color: #D1332D;
        background: #FFF5F5;
    }

    .mi-tab.active {
        color: #D1332D;
        border-bottom-color: #D1332D;
    }

    /* HEADER */
    .mi-header {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .mi-search {
        flex: 1;
        min-width: 250px;
        display: flex;
        align-items: center;
        background: white;
        border: 2px solid #E0E0E0;
        border-radius: 8px;
        padding: 0.8rem 1rem;
        transition: all 0.3s ease;
    }

    .mi-search:focus-within {
        border-color: #D1332D;
        box-shadow: 0 0 0 3px rgba(209, 51, 45, 0.1);
    }

    .mi-search input {
        flex: 1;
        border: none;
        background: transparent;
        outline: none;
        font-size: 0.95rem;
        color: #333;
    }

    .mi-search input::placeholder {
        color: #BBB;
    }

    .mi-btn {
        padding: 0.8rem 1.5rem;
        background: linear-gradient(135deg, #D1332D 0%, #A00D0A 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        white-space: nowrap;
        box-shadow: 0 2px 8px rgba(209, 51, 45, 0.2);
    }

    .mi-btn:hover {
        background: linear-gradient(135deg, #A00D0A 0%, #7A0A07 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(209, 51, 45, 0.3);
    }

    /* STATUS */
    .mi-status {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
        border: 2px solid #FFB74D;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        color: #E65100;
        font-weight: 500;
    }

    .mi-status.live {
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        border-color: #81C784;
        color: #1B5E20;
    }

    /* FILTERS */
    .mi-filters {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .mi-chip {
        padding: 0.6rem 1.2rem;
        background: white;
        border: 2px solid #E0E0E0;
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        white-space: nowrap;
        color: #555;
    }

    .mi-chip:hover {
        border-color: #D1332D;
        color: #D1332D;
        background: #FFF5F5;
    }

    .mi-chip.active {
        background: linear-gradient(135deg, #D1332D 0%, #A00D0A 100%);
        color: white;
        border-color: transparent;
        font-weight: 600;
    }

    /* GRID */
    .mi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .mi-card {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .mi-card:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .mi-card-image {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, #D1332D 0%, #A00D0A 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        overflow: hidden;
    }

    .mi-card-body {
        padding: 1.25rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .mi-badge {
        display: inline-block;
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 4px;
        font-weight: 600;
        margin-bottom: 0.5rem;
        width: fit-content;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mi-badge-lechon { background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%); color: #E65100; }
    .mi-badge-pig-farming { background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%); color: #01579B; }
    .mi-badge-pork-market { background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%); color: #B71C1C; }
    .mi-badge-agriculture { background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); color: #1B5E20; }
    .mi-badge-business-news { background: linear-gradient(135deg, #F5F5F5 0%, #EEEEEE 100%); color: #212121; }

    .mi-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #222;
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    .mi-card-description {
        font-size: 0.9rem;
        color: #555;
        line-height: 1.5;
        margin-bottom: 1rem;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mi-card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: #888;
        padding-bottom: 1rem;
        border-bottom: 1px solid #F0F0F0;
        margin-bottom: 1rem;
    }

    .mi-card-actions {
        display: flex;
        gap: 0.5rem;
    }

    .mi-card-btn {
        flex: 1;
        padding: 0.6rem 0.8rem;
        border: 1px solid #E0E0E0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
        color: #D1332D;
    }

    .mi-card-btn:hover {
        border-color: #D1332D;
        background: #FFF5F5;
        transform: translateY(-1px);
    }

    .mi-card-btn.pinned {
        background: #D1332D;
        color: white;
        border-color: #D1332D;
    }

    .mi-card-btn-primary {
        background: #D1332D;
        color: white;
        border-color: #D1332D;
    }

    .mi-card-btn-primary:hover {
        background: #A00D0A;
        border-color: #A00D0A;
        transform: translateY(-1px);
    }

    /* EMPTY STATE */
    .mi-empty {
        text-align: center;
        padding: 2rem;
        color: #999;
    }

    .mi-empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .mi-empty-title {
        font-size: 1rem;
        font-weight: 600;
        color: #555;
    }

    /* SAVED TAB LAYOUT */
    .mi-saved-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .mi-section {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        padding: 1.5rem;
    }

    .mi-section-title {
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    .mi-list-item {
        border: 1px solid #E0E0E0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.5rem;
    }

    .mi-list-item-title {
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        color: #333;
    }

    .mi-list-item-desc {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.75rem;
        line-height: 1.4;
    }

    .mi-list-actions {
        display: flex;
        gap: 0.5rem;
    }

    .mi-list-btn {
        flex: 1;
        padding: 0.4rem 0.6rem;
        border: 1px solid #E0E0E0;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .mi-list-btn:hover {
        border-color: #D1332D;
        color: #D1332D;
    }

    /* CALENDAR */
    .mi-calendar {
        margin-bottom: 1rem;
    }

    .mi-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .mi-calendar-nav {
        display: flex;
        gap: 0.5rem;
    }

    .mi-calendar-nav-btn {
        padding: 0.3rem 0.6rem;
        border: 1px solid #E0E0E0;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.8rem;
    }

    .mi-calendar-nav-btn:hover {
        background: #FFF5F5;
        border-color: #D1332D;
    }

    .mi-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.3rem;
    }

    .mi-calendar-day-header {
        text-align: center;
        font-weight: 700;
        color: #666;
        padding: 0.4rem 0;
        font-size: 0.8rem;
    }

    .mi-calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #E0E0E0;
        border-radius: 4px;
        background: white;
        cursor: pointer;
        font-weight: 600;
        color: #333;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .mi-calendar-day:hover {
        border-color: #D1332D;
        background: #FFF5F5;
    }

    .mi-calendar-day.today {
        background: #D1332D;
        color: white;
        border-color: #D1332D;
    }

    .mi-calendar-day.has-items {
        background: #FFF3E0;
        border-color: #FFB74D;
    }

    .mi-note {
        background: #FFF3E0;
        border: 1px solid #FFD54F;
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .mi-note-title {
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 0.4rem;
        color: #333;
    }

    .mi-note-content {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .mi-note-date {
        font-size: 0.75rem;
        color: #999;
        margin-bottom: 0.5rem;
    }

    .mi-note-delete {
        padding: 0.3rem 0.6rem;
        background: #D1332D;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s ease;
    }

    .mi-note-delete:hover {
        background: #A00D0A;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .mi-saved-layout {
            grid-template-columns: 1fr;
        }
    }

    /* MODAL */
    .mi-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .mi-modal.active {
        display: flex;
    }

    .mi-modal-content {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .mi-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #E0E0E0;
    }

    .mi-modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #999;
        transition: all 0.3s ease;
    }

    .mi-modal-close:hover {
        color: #333;
    }

    .mi-modal-body {
        padding: 1.5rem;
    }

    .mi-form-group {
        margin-bottom: 1.5rem;
    }

    .mi-form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }

    .mi-form-input,
    .mi-form-textarea {
        width: 100%;
        padding: 0.8rem;
        border: 2px solid #E0E0E0;
        border-radius: 6px;
        font-size: 0.9rem;
        font-family: inherit;
        transition: all 0.3s ease;
    }

    .mi-form-input:focus,
    .mi-form-textarea:focus {
        outline: none;
        border-color: #D1332D;
        box-shadow: 0 0 0 3px rgba(209, 51, 45, 0.1);
    }

    .mi-form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .mi-modal-footer {
        padding: 1.5rem;
        border-top: 1px solid #E0E0E0;
        display: flex;
        gap: 0.75rem;
    }

    .mi-modal-btn {
        flex: 1;
        padding: 0.8rem 1rem;
        border: 1px solid #E0E0E0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
    }

    .mi-modal-btn:hover {
        border-color: #D1332D;
        color: #D1332D;
    }

    .mi-modal-btn-primary {
        background: linear-gradient(135deg, #D1332D 0%, #A00D0A 100%);
        color: white;
        border-color: transparent;
    }

    .mi-modal-btn-primary:hover {
        background: linear-gradient(135deg, #A00D0A 0%, #7A0A07 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(209, 51, 45, 0.3);
    }
</style>

<div class="mi-container">
    <!-- TABS -->
    <div class="mi-tabs">
        <button class="mi-tab active" onclick="switchTab('trends')"> Market Trends</button>
        <button class="mi-tab" onclick="switchTab('saved')"> Saved & Notes</button>
    </div>

    <!-- TAB 1: TRENDS -->
    <div id="trends-tab" class="mi-tab-content" style="display: block;">
        <div class="mi-header">
            <div class="mi-search">
                <input type="text" id="searchInput" placeholder="🔍 Search trends..." onkeyup="searchTrends()">
            </div>
            <button class="mi-btn" id="refreshBtn" onclick="refreshTrends()">↻ Refresh</button>
        </div>

        <div class="mi-status" id="statusBar">⟳ Loading live market data...</div>

        <div class="mi-filters" id="categoryFilters">
            <button class="mi-chip active" onclick="filterByCategory('')">All</button>
            <button class="mi-chip" onclick="filterByCategory('Lechon')"> Lechon</button>
            <button class="mi-chip" onclick="filterByCategory('Pig Farming')"> Pig Farming</button>
            <button class="mi-chip" onclick="filterByCategory('Pork Market')"> Pork Market</button>
            <button class="mi-chip" onclick="filterByCategory('Agriculture')"> Agriculture</button>
        </div>

        <div id="trendsContainer" class="mi-grid"></div>
    </div>

    <!-- TAB 2: SAVED & NOTES -->
    <div id="saved-tab" class="mi-tab-content" style="display: none;">
        <div class="mi-saved-layout">
            <!-- LEFT: CALENDAR -->
            <div class="mi-section">
                <h3 class="mi-section-title"> Calendar</h3>
                <div class="mi-calendar-header">
                    <button class="mi-calendar-nav-btn" onclick="previousMonth()">← Prev</button>
                    <span id="calendarMonth" style="font-weight: 700;">Month</span>
                    <button class="mi-calendar-nav-btn" onclick="nextMonth()">Next →</button>
                </div>
                <div class="mi-calendar-grid" id="calendarGrid"></div>
            </div>

            <!-- RIGHT COLUMN -->
            <div>
                <!-- TOP: PINNED -->
                <div class="mi-section" style="margin-bottom: 1.5rem;">
                    <h3 class="mi-section-title"> Pinned Trends</h3>
                    <div id="pinnedContainer">
                        <div class="mi-empty">
                            <div class="mi-empty-icon"></div>
                            <div class="mi-empty-title">No Pinned Trends</div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM: NOTES -->
                <div class="mi-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="mi-section-title" style="margin-bottom: 0;"> Notes</h3>
                        <button class="mi-btn" onclick="addNote()" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">+ Add</button>
                    </div>
                    <div id="notesContainer">
                        <div class="mi-empty">
                            <div class="mi-empty-icon"></div>
                            <div class="mi-empty-title">No Notes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NOTE MODAL -->
<div id="noteModal" class="mi-modal">
    <div class="mi-modal-content">
        <div class="mi-modal-header">
            <h2 style="margin: 0; font-size: 1.2rem;">📝 Add Note</h2>
            <button class="mi-modal-close" onclick="closeNoteModal()">×</button>
        </div>
        <div class="mi-modal-body">
            <div class="mi-form-group">
                <label class="mi-form-label">Title</label>
                <input type="text" id="noteTitle" class="mi-form-input" placeholder="Note title...">
            </div>
            <div class="mi-form-group">
                <label class="mi-form-label">Content</label>
                <textarea id="noteContent" class="mi-form-textarea" placeholder="Note content..."></textarea>
            </div>
            <div class="mi-form-group">
                <label class="mi-form-label">Date</label>
                <input type="date" id="noteDate" class="mi-form-input">
            </div>
        </div>
        <div class="mi-modal-footer">
            <button class="mi-modal-btn mi-modal-btn-primary" onclick="saveNote()">Save Note</button>
            <button class="mi-modal-btn" onclick="closeNoteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
    let allTrends = [];
    let filteredTrends = [];
    let currentCategory = '';
    let currentSearch = '';
    let pinnedTrendIds = new Set();
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth();
    let marketauxApiKey = '6UUSDGb1bh3sIbLuwmMH92kiTn1zqbZhKLT2pU2g';

    // INIT
    document.addEventListener('DOMContentLoaded', function() {
        loadPinnedTrends();
        renderCalendar();
        fetchFromMarketAux();
        loadNotes();
    });

    // FETCH DATA
    function fetchFromMarketAux(callback) {
        const statusBar = document.getElementById('statusBar');
        statusBar.textContent = '⟳ Fetching live Philippine market data...';
        
        const url = `https://api.marketaux.com/v1/news/all?countries=ph&limit=100&api_token=${marketauxApiKey}`;
        
        fetch(url, { timeout: 10000 })
            .then(r => r.json())
            .then(data => {
                if (data.data && data.data.length > 0) {
                    const filtered = data.data.filter(a => {
                        const text = (a.title + ' ' + (a.description || '')).toLowerCase();
                        return text.includes('pig') || text.includes('pork') || text.includes('lechon') || 
                               text.includes('swine') || text.includes('hog') || text.includes('farm') || 
                               text.includes('livestock') || text.includes('meat') || text.includes('agriculture');
                    });
                    
                    const use = filtered.length > 0 ? filtered : data.data.slice(0, 10);
                    
                    allTrends = use.slice(0, 20).map((a, i) => ({
                        id: i + 1,
                        title: a.title,
                        description: a.description || a.content || 'Market intelligence',
                        category: categorize(a.title + ' ' + (a.description || '')),
                        source: a.source || 'MarketAux',
                        published_at: a.published_at || new Date().toISOString(),
                        emoji: getEmoji(a.title + ' ' + (a.description || '')),
                        url: a.url || '#'
                    }));
                    
                    filteredTrends = allTrends;
                    renderTrends();
                    statusBar.classList.add('live');
                    statusBar.textContent = '✓ Live data from MarketAux - ' + allTrends.length + ' articles';
                    if (callback) callback();
                } else {
                    throw new Error('No data');
                }
            })
            .catch(e => {
                console.error(e);
                statusBar.textContent = '📊 Using demo market data';
                loadDemo();
                if (callback) callback();
            });
    }

    function categorize(text) {
        text = text.toLowerCase();
        if (text.includes('lechon') || text.includes('roast')) return 'Lechon';
        if (text.includes('pig') || text.includes('swine')) return 'Pig Farming';
        if (text.includes('pork')) return 'Pork Market';
        if (text.includes('farm') || text.includes('livestock')) return 'Agriculture';
        return 'Business News';
    }

    function getEmoji(text) {
        text = text.toLowerCase();
        if (text.includes('lechon') || text.includes('roast')) return '🍖';
        if (text.includes('pig') || text.includes('swine')) return '🐷';
        if (text.includes('price')) return '💰';
        return '📰';
    }

    function loadDemo() {
        allTrends = [
            {id: 1, title: 'Pork Prices Rise 8% Q3 2026', description: 'Market analysis shows pig prices climbing due to supply constraints', category: 'Pork Market', source: 'Market Intel', published_at: new Date().toISOString(), emoji: '🍖', url: '#'},
            {id: 2, title: 'Lechon Festival Boosts Local Tourism', description: 'Annual event drives 40% increase in roasted pig orders', category: 'Lechon', source: 'Business News', published_at: new Date(Date.now() - 2*24*60*60*1000).toISOString(), emoji: '🍖', url: '#'},
            {id: 3, title: 'Feed Costs Stabilize', description: 'Corn supply improvement reduces feed prices by 4%', category: 'Pig Farming', source: 'Agri News', published_at: new Date(Date.now() - 5*24*60*60*1000).toISOString(), emoji: '🐷', url: '#'},
            {id: 4, title: 'Sustainable Farming Program', description: 'New eco-friendly certification offers 15-20% market premium', category: 'Agriculture', source: 'Gov News', published_at: new Date(Date.now() - 7*24*60*60*1000).toISOString(), emoji: '🌾', url: '#'}
        ];
        filteredTrends = allTrends;
        renderTrends();
    }

    // RENDER
    function renderTrends() {
        const c = document.getElementById('trendsContainer');
        if (filteredTrends.length === 0) {
            c.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem;"><div style="font-size: 3rem; margin-bottom: 1rem;">📭</div><div style="color: #999;">No trends found</div></div>';
            return;
        }
        
        c.innerHTML = filteredTrends.map(t => `
            <div class="mi-card">
                <div class="mi-card-image">${t.emoji}</div>
                <div class="mi-card-body">
                    <span class="mi-badge mi-badge-${t.category.toLowerCase().replace(/\s+/g, '-')}">${t.category}</span>
                    <h3 class="mi-card-title">${t.title}</h3>
                    <p class="mi-card-description">${t.description}</p>
                    <div class="mi-card-meta">
                        <span>${formatDate(t.published_at)}</span>
                    </div>
                </div>
                <div class="mi-card-actions">
                    <button class="mi-card-btn ${pinnedTrendIds.has(t.id) ? 'pinned' : ''}" onclick="togglePin(${t.id})">📌 Pin</button>
                    <button class="mi-card-btn mi-card-btn-primary" onclick="window.open('${t.url}', '_blank')">Read More</button>
                </div>
            </div>
        `).join('');
    }

    function formatDate(d) {
        const date = new Date(d);
        const today = new Date();
        if (date.toDateString() === today.toDateString()) return 'Today';
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // SEARCH & FILTER
    function searchTrends() {
        currentSearch = document.getElementById('searchInput').value.toLowerCase();
        applyFilters();
    }

    function filterByCategory(cat) {
        currentCategory = cat;
        document.querySelectorAll('.mi-chip').forEach(c => c.classList.remove('active'));
        event.target.classList.add('active');
        applyFilters();
    }

    function applyFilters() {
        filteredTrends = allTrends.filter(t => {
            const matchCat = !currentCategory || t.category === currentCategory;
            const matchSearch = !currentSearch || t.title.toLowerCase().includes(currentSearch) || t.description.toLowerCase().includes(currentSearch);
            return matchCat && matchSearch;
        });
        renderTrends();
    }

    // PIN
    function togglePin(id) {
        if (pinnedTrendIds.has(id)) {
            pinnedTrendIds.delete(id);
        } else {
            pinnedTrendIds.add(id);
        }
        localStorage.setItem('pinnedTrends', JSON.stringify(Array.from(pinnedTrendIds)));
        renderTrends();
        loadPinnedTrends();
    }

    function loadPinnedTrends() {
        const stored = localStorage.getItem('pinnedTrends');
        pinnedTrendIds = new Set(stored ? JSON.parse(stored) : []);
        
        const c = document.getElementById('pinnedContainer');
        const pinned = allTrends.filter(t => pinnedTrendIds.has(t.id));
        
        if (pinned.length === 0) {
            c.innerHTML = '<div class="mi-empty"><div class="mi-empty-icon">📌</div><div class="mi-empty-title">No Pinned Trends</div></div>';
        } else {
            c.innerHTML = pinned.map(t => `
                <div class="mi-list-item">
                    <div class="mi-list-item-title">${t.title}</div>
                    <div class="mi-list-item-desc">${t.description.substring(0, 80)}...</div>
                    <div class="mi-list-actions">
                        <button class="mi-list-btn" onclick="togglePin(${t.id})">Unpin</button>
                        <button class="mi-list-btn" onclick="window.open('${t.url}', '_blank')">View</button>
                    </div>
                </div>
            `).join('');
        }
    }

    // NOTES
    function addNote() {
        document.getElementById('noteTitle').value = '';
        document.getElementById('noteContent').value = '';
        document.getElementById('noteDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('noteModal').classList.add('active');
    }

    function closeNoteModal() {
        document.getElementById('noteModal').classList.remove('active');
    }

    function saveNote() {
        const title = document.getElementById('noteTitle').value.trim();
        const content = document.getElementById('noteContent').value.trim();
        const date = document.getElementById('noteDate').value;
        
        if (!title || !content || !date) {
            alert('Please fill all fields');
            return;
        }
        
        const notes = JSON.parse(localStorage.getItem('marketIntelligenceNotes') || '[]');
        notes.push({ 
            id: Date.now(), 
            title, 
            content, 
            date: new Date(date).toISOString() 
        });
        localStorage.setItem('marketIntelligenceNotes', JSON.stringify(notes));
        loadNotes();
        renderCalendar();
        closeNoteModal();
    }

    function loadNotes() {
        const notes = JSON.parse(localStorage.getItem('marketIntelligenceNotes') || '[]');
        const c = document.getElementById('notesContainer');
        
        if (notes.length === 0) {
            c.innerHTML = '<div class="mi-empty"><div class="mi-empty-icon">📝</div><div class="mi-empty-title">No Notes</div></div>';
        } else {
            c.innerHTML = notes.map(n => `
                <div class="mi-note">
                    <div class="mi-note-title">${n.title}</div>
                    <div class="mi-note-content">${n.content.substring(0, 80)}...</div>
                    <div class="mi-note-date">${formatDate(n.date)}</div>
                    <button class="mi-note-delete" onclick="deleteNote(${n.id})">Delete</button>
                </div>
            `).join('');
        }
    }

    function deleteNote(id) {
        const notes = JSON.parse(localStorage.getItem('marketIntelligenceNotes') || '[]');
        const filtered = notes.filter(n => n.id !== id);
        localStorage.setItem('marketIntelligenceNotes', JSON.stringify(filtered));
        loadNotes();
        renderCalendar();
    }

    // CALENDAR
    function renderCalendar() {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        document.getElementById('calendarMonth').textContent = months[currentMonth] + ' ' + currentYear;
        
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
        
        const notes = JSON.parse(localStorage.getItem('marketIntelligenceNotes') || '[]');
        const noteDates = new Set(notes.map(n => new Date(n.date).getDate()));
        
        const grid = document.getElementById('calendarGrid');
        grid.innerHTML = '';
        
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(d => {
            const h = document.createElement('div');
            h.textContent = d;
            h.className = 'mi-calendar-day-header';
            grid.appendChild(h);
        });
        
        for (let i = firstDay - 1; i >= 0; i--) {
            const d = document.createElement('div');
            d.textContent = daysInPrevMonth - i;
            d.style.cssText = 'background: #F5F5F5; color: #CCC;';
            d.className = 'mi-calendar-day';
            grid.appendChild(d);
        }
        
        const today = new Date();
        for (let i = 1; i <= daysInMonth; i++) {
            const d = document.createElement('div');
            d.textContent = i;
            d.className = 'mi-calendar-day';
            
            const isToday = i === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear();
            const hasNote = noteDates.has(i);
            
            if (isToday) d.classList.add('today');
            if (hasNote) d.classList.add('has-items');
            
            grid.appendChild(d);
        }
        
        const totalCells = grid.children.length - 7;
        const remaining = 42 - totalCells;
        for (let i = 1; i <= remaining; i++) {
            const d = document.createElement('div');
            d.textContent = i;
            d.style.cssText = 'background: #F5F5F5; color: #CCC;';
            d.className = 'mi-calendar-day';
            grid.appendChild(d);
        }
    }

    function previousMonth() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar();
    }

    function nextMonth() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    }

    // TABS
    function switchTab(tab) {
        document.querySelectorAll('.mi-tab-content').forEach(t => t.style.display = 'none');
        document.querySelectorAll('.mi-tab').forEach(t => t.classList.remove('active'));
        
        document.getElementById(tab + '-tab').style.display = 'block';
        event.target.classList.add('active');
        
        if (tab === 'saved') renderCalendar();
    }

    // REFRESH
    function refreshTrends() {
        const btn = document.getElementById('refreshBtn');
        btn.classList.add('loading');
        btn.disabled = true;
        
        fetchFromMarketAux(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
        });
    }
</script>
