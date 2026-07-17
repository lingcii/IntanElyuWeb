<?php
require_once __DIR__ . '/../../session-bridge.php';
require_once __DIR__ . '/../../laravel-api-bridge.php';
if ($_SESSION['user_role'] !== 'picto') {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'PICTO Tourist Spots';

ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="../../css/LUPTO/tourist-spots.css">
<link rel="stylesheet" href="../../css/LUPTO/dashboard.css">
<link rel="stylesheet" href="../../css/LUPTO/map-view.css">

<?php
$extraHeadContent = ob_get_clean();

ob_start();
?>
  <!-- Summary Cards -->
    <div class="lupto-kpi-grid" style="grid-template-columns: repeat(5, 1fr);">
        <div class="lupto-kpi-card" data-kpi="total-spots">
            <div class="lupto-kpi-info">
                <h4>Total Tourist Spots</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <span class="lupto-kpi-trend trend-neutral" id="kpi-trend-total"><i class="fas fa-layer-group"></i> All spots</span>
            </div>
            <div class="lupto-kpi-icon bg-blue"><i class="fas fa-map-location-dot"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="approved-spots">
            <div class="lupto-kpi-info">
                <h4>Total Approved Tourist Spots</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <span class="lupto-kpi-trend trend-up" id="kpi-trend-approved"><i class="fas fa-check"></i> Approved</span>
            </div>
            <div class="lupto-kpi-icon bg-green"><i class="fas fa-circle-check"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="pending-spots">
            <div class="lupto-kpi-info">
                <h4>Total Pending Tourist Spots</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <span class="lupto-kpi-trend trend-neutral" id="kpi-trend-pending"><i class="fas fa-clock"></i> Pending</span>
            </div>
            <div class="lupto-kpi-icon" style="background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #F59E0B;"><i class="fas fa-hourglass-half"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="declined-spots">
            <div class="lupto-kpi-info">
                <h4>Total Declined Tourist Spots</h4>
                <span class="lupto-kpi-value"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <span class="lupto-kpi-trend trend-down" id="kpi-trend-declined"><i class="fas fa-times"></i> Rejected</span>
            </div>
            <div class="lupto-kpi-icon bg-red" style="background: linear-gradient(135deg, #FEE2E2, #FECACA); color: #DC2626;"><i class="fas fa-circle-xmark"></i></div>
        </div>
        <div class="lupto-kpi-card" data-kpi="most-visited-category">
            <div class="lupto-kpi-info">
                <h4>Most Visited Category</h4>
                <span class="lupto-kpi-value" style="font-size: 20px;"><i class="fas fa-spinner fa-spin" style="font-size:16px;color:#9CA3AF;"></i></span>
                <span class="lupto-kpi-trend trend-up" id="kpi-trend-category"><i class="fas fa-crown"></i> Top category</span>
            </div>
            <div class="lupto-kpi-icon bg-gold" style="background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #D97706;"><i class="fas fa-trophy"></i></div>
        </div>
    </div>

    <!-- PICTO Full Screen Map Wrapper -->
    <div class="lupto-fullscreen-map-wrapper">
        <div class="lupto-map-controls-panel">
            <h3 class="card-title" style="margin:0;">
                <i class="fas fa-map"></i> La Union Interactive Map
            </h3>
            <div class="map-view-toolbar">
                <div class="map-tabs" aria-label="Map layer switcher">
                    <button class="map-tab active" data-view="street" type="button">
                        <i class="fas fa-map"></i> Street Map
                    </button>
                    <button class="map-tab" data-view="satellite" type="button">
                        <i class="fas fa-satellite"></i> Satellite
                    </button>
                </div>
                <button data-action="open-create-form" class="btn btn-primary" style="margin-left: auto;">
                    <i class="fas fa-plus"></i> Add Spot
                </button>
            </div>
        </div>
        
        <div class="map-wrapper">
            <div id="lupto-map" class="lupto-dedicated-map"></div>
            
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            
            <div class="sidebar-container" id="sidebarContainer" role="dialog" aria-labelledby="sidebarTitle">
                <div class="sidebar-header">
                    <div class="sidebar-header-left">
                        <button class="sidebar-back-btn hidden" id="sidebarBackBtn" aria-label="Go back">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <h3 id="sidebarTitle">Tourist Spots</h3>
                    </div>
                    <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="sidebar-content" id="sidebarContent">
                </div>
            </div>
        </div>
    </div>

<!-- -- Filter Bar -->
<div class="filter-bar">
    <div class="filter-bar-inner">
        <div class="filter-field filter-field-search">
            <label class="filter-label"><i class="fas fa-search"></i> Search</label>
            <div class="filter-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Spot name or keyword..." class="filter-input">
            </div>
        </div>
    </div>
    <div class="filter-bar-right">
        <div class="filter-field">
            <label class="filter-label"><i class="fas fa-map-marker-alt"></i> Municipality</label>
            <select id="filterMunicipality" class="filter-select">
                <option value="">All Municipalities</option>
            </select>
        </div>
        <div class="filter-field" style="position:relative;">
            <label class="filter-label"><i class="fas fa-tag"></i> Category</label>
            <div id="catFilterBtn" class="filter-select" style="cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between;gap:6px;min-width:140px;" onclick="toggleCatDropdown(event)">
                <span id="catFilterLabel">All Categories</span>
                <i class="fas fa-chevron-down" style="font-size:10px;color:#9CA3AF;transition:transform .2s;" id="catChevron"></i>
            </div>
            <div id="catFilterDropdown" style="display:none;position:absolute;top:100%;left:0;z-index:999;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:8px 0;min-width:180px;margin-top:4px;max-height:240px;overflow-y:auto;">
                <div style="padding:6px 14px;font-size:11px;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Select categories</div>
                <?php
                $cats = ['Beach','Mountain','Waterfalls','River','Lake','Island','Cave','Volcano','Forest','Nature Park','Marine Sanctuary','Wildlife Sanctuary','Historical','Cultural Heritage','Religious','Museum','Monument','Landmark','Viewpoint','Adventure','Hiking','Camping','Farm','Eco-Tourism','Garden','Park','Recreation','Hot Spring','Cold Spring','Food Destination','Shopping','Festival Venue','Resort','Other'];
                foreach ($cats as $c): ?>
                <label style="display:flex;align-items:center;gap:10px;padding:7px 14px;cursor:pointer;font-size:13px;transition:background .15s;" onmouseenter="this.style.background='#F8FAFC'" onmouseleave="this.style.background='transparent'">
                    <input type="checkbox" class="cat-filter-chk" value="<?= $c ?>" onchange="onCatFilterChange()" style="accent-color:#2563EB;width:15px;height:15px;cursor:pointer;">
                    <?= $c ?>
                </label>
                <?php endforeach; ?>
                <div style="border-top:1px solid #F1F5F9;margin:6px 0 2px;"></div>
                <button onclick="clearCatFilter()" style="width:100%;background:none;border:none;padding:7px 14px;text-align:left;font-size:12px;color:#6B7280;cursor:pointer;" onmouseenter="this.style.color='#2563EB'" onmouseleave="this.style.color='#6B7280'"><i class="fas fa-times-circle"></i> Clear selection</button>
            </div>
        </div>
        <div class="filter-field">
            <label class="filter-label"><i class="fas fa-circle-dot"></i> Status</label>
            <select id="filterStatus" class="filter-select">
                <option value="">All Status</option>
                <option value="EXISTING">EXISTING</option>
                <option value="POTENTIAL">POTENTIAL</option>
                <option value="EMERGING">EMERGING</option>
            </select>
        </div>
        <div class="filter-field">
            <label class="filter-label"><i class="fas fa-sort"></i> Sort By</label>
            <select id="sortSpots" class="filter-select">
                <option value="">Default (Pending First)</option>
                <option value="points_desc">Highest Points</option>
                <option value="points_asc">Lowest Points</option>
            </select>
        </div>
        <span class="filter-count"><span id="spotCount">0</span> tourist spot(s)</span>
        <div class="view-toggle">
            <button class="active" id="viewCards" title="Card View"><i class="fas fa-th"></i></button>
            <button id="viewTable" title="Table View"><i class="fas fa-list"></i></button>
        </div>
    </div>
</div>

<!-- -- Spot Detail Modal  -->
<div class="modal" id="spotModal">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <h2 id="modalTitle">Spot Details</h2>
            <button class="modal-close" id="closeSpotModal">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center;padding:40px;color:#9CA3AF;">
                <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
            </div>
        </div>
    </div>
</div>

<!-- -- Cards Grid -->
<div class="cards-grid" id="cardsView">
    <div style="text-align:center;padding:40px;color:#9CA3AF;grid-column:1/-1;">
        <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
        <p style="margin-top:12px;">Loading tourist spots...</p>
    </div>
</div>

<!-- -- Table View -->
<div id="tableView" style="display:none; margin-bottom:24px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Spot ID</th>
                <th>Spot Name</th>
                <th>Municipality</th>
                <th>Category</th>
                <th>Classification</th>
                <th>Points</th>
                <th>Approval Status</th>
                <th>Entry Fee</th>
                <th>Submitted On</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Add / Edit Spot Modal -->
<div class="modal" id="spotFormModal">
    <div class="modal-content spot-form-modal-content">
        <div class="sfm-header">
            <div class="sfm-header-left">
                <div class="sfm-header-icon"><i class="fas fa-map-marked-alt"></i></div>
                <div>
                    <h2 id="formModalTitle">Add New Spot</h2>
                    <p class="sfm-header-sub">Fill in the details below to register a tourist spot</p>
                </div>
            </div>
            <button type="button" class="sfm-close-btn" data-action="close-form-modal" aria-label="Close Modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sfm-body">
            <form id="spotForm">
                <input type="hidden" id="spotId" value="">
                <input type="hidden" id="municipalityId" value="">

                <div class="sfm-section">
                    <div class="sfm-section-label">
                        <i class="fas fa-map-marker-alt"></i> Location
                    </div>
                   
                    <div class="sfm-map-container">
                        <div id="modalMap" style="height:100%;width:100%;"></div>
                        <div class="sfm-map-hint">
                            <i class="fas fa-hand-pointer"></i> Click map or drag pin to set location
                        </div>
                    </div>

                    <div class="sfm-field">
                        <label class="sfm-label" for="spotMunicipality">
                            Municipality <span class="sfm-required">*</span>
                        </label>
                        <select id="spotMunicipality" class="sfm-select" required>
                            <option value="">— Select Municipality —</option>
                        </select>
                    </div>

                    <div class="sfm-location-row">
                        <div class="sfm-location-barangay">
                            <label class="sfm-label" for="spotBarangay">Barangay</label>
                            <select id="spotBarangay" class="sfm-select">
                                <option value="">— Select Barangay —</option>
                            </select>
                        </div>
                        <div class="sfm-location-coord">
                            <label class="sfm-label" for="spotLatitude">
                                <i class="fas fa-globe" style="color:#6B7280;margin-right:3px;"></i> Latitude
                            </label>
                            <input type="number" id="spotLatitude" class="sfm-input" step="any"
                                   placeholder="e.g., 16.3278">
                        </div>
                        <div class="sfm-location-coord">
                            <label class="sfm-label" for="spotLongitude">
                                <i class="fas fa-map" style="color:#6B7280;margin-right:3px;"></i> Longitude
                            </label>
                            <input type="number" id="spotLongitude" class="sfm-input" step="any"
                                   placeholder="e.g., 120.3663">
                        </div>
                    </div>
                    
                </div>

                <div class="sfm-section">
                    <div class="sfm-section-label">
                        <i class="fas fa-images"></i> Photo Upload
                    </div>
                    <label id="imageUploadArea" class="sfm-upload-area">
                        <div class="sfm-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p class="sfm-upload-title">Click or drag to upload</p>
                        <p class="sfm-upload-sub">JPEG / PNG &middot; Max 5MB per file</p>
                        <input type="file" id="spotImages" accept="image/jpeg,image/png,image/jpg,.jpg,.jpeg,.png" multiple hidden>
                    </label>
                    <div id="imagePreviews" class="sfm-image-previews"></div>
                </div>

                <div class="sfm-section">
                    <div class="sfm-section-label">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </div>

                    <div class="sfm-field">
                        <label class="sfm-label" for="spotName">
                            Spot Name <span class="sfm-required">*</span>
                        </label>
                        <input type="text" id="spotName" class="sfm-input" maxlength="100" required
                               placeholder="e.g., spot name">
                        <div class="sfm-char-count"><span id="nameCharCount">0</span>/100</div>
                    </div>

                    <div class="sfm-field">
                        <label class="sfm-label">
                            Categories <span class="sfm-required">*</span>
                        </label>
                        <div class="sfm-category-dropdown-wrap" style="position:relative; width:100%;">
                            <div id="formCatDropdownBtn" class="sfm-select" style="cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between;gap:6px;min-height:38px;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;background:#fff;" onclick="toggleFormCatDropdown(event)">
                                <span id="formCatDropdownLabel" style="color:#9CA3AF;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:90%;">Select Categories...</span>
                                <i class="fas fa-chevron-down" style="font-size:12px;color:#9CA3AF;transition:transform .2s;" id="formCatChevron"></i>
                            </div>
                            <div id="formCatDropdown" style="display:none;position:absolute;top:100%;left:0;z-index:9999;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:8px 0;width:100%;max-height:240px;overflow-y:auto;margin-top:4px;">
                                <div style="padding:4px 14px;font-size:11px;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Choose one or more categories</div>
                                <?php 
                                $formCategories = [
                                    'Beach' => 'umbrella-beach',
                                    'Mountain' => 'mountain',
                                    'Waterfalls' => 'water',
                                    'River' => 'water',
                                    'Lake' => 'water',
                                    'Island' => 'umbrella-beach',
                                    'Cave' => 'mountain',
                                    'Volcano' => 'mountain',
                                    'Forest' => 'tree',
                                    'Nature Park' => 'tree',
                                    'Marine Sanctuary' => 'fish',
                                    'Wildlife Sanctuary' => 'paw',
                                    'Historical' => 'landmark',
                                    'Cultural Heritage' => 'landmark',
                                    'Religious' => 'church',
                                    'Museum' => 'museum',
                                    'Monument' => 'monument',
                                    'Landmark' => 'landmark',
                                    'Viewpoint' => 'binoculars',
                                    'Adventure' => 'hiking',
                                    'Hiking' => 'hiking',
                                    'Camping' => 'campground',
                                    'Farm' => 'seedling',
                                    'Eco-Tourism' => 'leaf',
                                    'Garden' => 'seedling',
                                    'Park' => 'tree',
                                    'Recreation' => 'bicycle',
                                    'Hot Spring' => 'hot-tub-person',
                                    'Cold Spring' => 'snowflake',
                                    'Food Destination' => 'utensils',
                                    'Shopping' => 'shopping-cart',
                                    'Festival Venue' => 'masks-theater',
                                    'Resort' => 'hotel',
                                    'Other' => 'star'
                                ];
                                foreach ($formCategories as $name => $icon): 
                                ?>
                                <div class="form-cat-item" data-value="<?= $name ?>" onclick="toggleFormCategory(this, event)" style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;transition:background .15s;font-size:14px;user-select:none;" onmouseenter="this.style.background='#F8FAFC'" onmouseleave="this.style.background='transparent'">
                                    <input type="checkbox" class="form-cat-chk" value="<?= $name ?>" style="pointer-events:none;accent-color:#2563EB;width:15px;height:15px;cursor:pointer;">
                                    <i class="fas fa-<?= $icon ?>" style="width:18px;text-align:center;color:#4B5563;font-size:13px;"></i>
                                    <span><?= $name ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="spotCategory" required>
                        <div class="sfm-selected-cats" id="selectedCatsDisplay" style="display:none;">
                            <span class="sfm-selected-label">Selected:</span>
                            <span id="selectedCatsList"></span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px;">
                        <div class="sfm-field" style="flex: 1; margin-bottom: 0;">
                            <label class="sfm-label" for="spotClassification">
                                Classification Status <span class="sfm-required">*</span>
                            </label>
                            <select id="spotClassification" class="sfm-select" required>
                                <option value="">— Select Status —</option>
                                <option value="EXISTING">Existing</option>
                                <option value="EMERGING">Emerging</option>
                                <option value="POTENTIAL">Potential</option>
                            </select>
                        </div>
                        <div class="sfm-field" style="flex: 1; margin-bottom: 0;">
                            <label class="sfm-label" for="spotPoints">
                                Points <span class="sfm-required">*</span>
                            </label>
                            <input type="number" id="spotPoints" class="sfm-input" readonly style="background-color: #F3F4F6; cursor: not-allowed;" placeholder="Points automatically assigned">
                        </div>
                    </div>
                </div>

                <div class="sfm-section">
                    <div class="sfm-section-label">
                        <i class="fas fa-align-left"></i> Spot Details
                    </div>

                    <div class="sfm-field">
                        <label class="sfm-label">
                            Fee Types
                        </label>
                        <div class="sfm-fee-dropdown-wrap" style="position:relative; width:100%;">
                            <div id="feeTypesBtn" class="sfm-select" style="cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between;gap:6px;min-height:38px;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;background:#fff;" onclick="toggleFeeTypesDropdown(event)">
                                <span id="feeTypesLabel" style="color:#9CA3AF;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">No Fees</span>
                                <i class="fas fa-chevron-down" style="font-size:12px;color:#9CA3AF;transition:transform .2s;" id="feeTypesChevron"></i>
                            </div>
                            <div id="feeTypesDropdown" style="display:none;position:absolute;top:100%;left:0;z-index:9999;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:8px 0;width:100%;max-height:240px;overflow-y:auto;margin-top:4px;">
                                <div style="padding:4px 14px;font-size:11px;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Select fee types</div>
                                <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;transition:background .15s;font-size:14px;" onmouseenter="this.style.background='#F8FAFC'" onmouseleave="this.style.background='transparent'">
                                    <input type="checkbox" class="fee-type-chk" value="entrance" onchange="onFeeTypeChange()" style="accent-color:#2563EB;width:15px;height:15px;cursor:pointer;">
                                    <i class="fas fa-ticket-alt" style="width:18px;text-align:center;color:#4B5563;font-size:13px;"></i>
                                    <span>Entrance Fee</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;transition:background .15s;font-size:14px;" onmouseenter="this.style.background='#F8FAFC'" onmouseleave="this.style.background='transparent'">
                                    <input type="checkbox" class="fee-type-chk" value="environmental" onchange="onFeeTypeChange()" style="accent-color:#2563EB;width:15px;height:15px;cursor:pointer;">
                                    <i class="fas fa-leaf" style="width:18px;text-align:center;color:#4B5563;font-size:13px;"></i>
                                    <span>Environmental Fee</span>
                                </label>
                            </div>
                        </div>
                        <input type="hidden" id="feeTypes" value="">
                    </div>

                    <div class="sfm-field" id="entranceFeeField" style="display:none;">
                        <label class="sfm-label" for="spotFee">
                            Entrance Fee Amount <span class="sfm-required">*</span>
                        </label>
                        <div class="sfm-fee-input-wrap">
                            <span class="sfm-fee-prefix">₱</span>
                            <input type="number" id="spotFee" class="sfm-input sfm-fee-input"
                                   min="0" step="0.01" value="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="sfm-field" id="environmentalFeeField" style="display:none;">
                        <label class="sfm-label" for="environmentalFee">
                            Environmental Fee Amount <span class="sfm-required">*</span>
                        </label>
                        <div class="sfm-fee-input-wrap">
                            <span class="sfm-fee-prefix">₱</span>
                            <input type="number" id="environmentalFee" class="sfm-input sfm-fee-input"
                                   min="0" step="0.01" value="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="sfm-two-col">
                        <div class="sfm-field">
                            <label class="sfm-label" for="spotOpeningTime">
                                <i class="fas fa-clock" style="color:#6B7280;margin-right:3px;"></i> Opening Time
                            </label>
                            <input type="time" id="spotOpeningTime" class="sfm-input">
                        </div>
                        <div class="sfm-field">
                            <label class="sfm-label" for="spotClosingTime">
                                <i class="fas fa-clock" style="color:#6B7280;margin-right:3px;"></i> Closing Time
                            </label>
                            <input type="time" id="spotClosingTime" class="sfm-input">
                        </div>
                    </div>

                    <div class="sfm-field" id="maintenance-field">
                        <label class="sfm-maintenance-toggle">
                            <input type="checkbox" id="spotIsMaintenance">
                            <span class="sfm-maintenance-icon"><i class="fas fa-tools"></i></span>
                            <span class="sfm-maintenance-text">Under Maintenance</span>
                            <span class="sfm-maintenance-hint">Hides this spot from tourist view</span>
                        </label>
                    </div>

                    <div class="sfm-field">
                        <label class="sfm-label" for="spotDescription">
                            Description <span class="sfm-required">*</span>
                        </label>
                        <textarea id="spotDescription" class="sfm-textarea" rows="4"
                                  maxlength="1000" required
                                  placeholder="Describe this tourist spot — its highlights, what makes it unique, activities available…"></textarea>
                        <div class="sfm-char-count"><span id="descCharCount">0</span>/1000</div>
                    </div>
                </div>

                <div class="sfm-footer">
                    <button type="button" class="sfm-btn-cancel" data-action="close-form-modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="sfm-btn-save" id="saveSpotBtn">
                        <i class="fas fa-check-circle" id="saveSpotIcon"></i>
                        <i class="fas fa-circle-notch fa-spin" id="saveSpotSpinner" style="display:none;"></i>
                        <span id="saveSpotLabel">Save Spot</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Save Confirmation Modal -->
<div class="modal" id="saveConfirmModal" style="z-index: 10002;">
    <div class="modal-content" style="max-width: 420px; border-radius: 16px; overflow: hidden;">
        <div style="background: #DBEAFE; padding: 28px 28px 16px 28px; text-align: center;">
            <div style="width: 56px; height: 56px; background: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                <i class="fas fa-save" style="color: white; font-size: 22px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1E3A8A;">Save Tourist Spot</h3>
        </div>
        <div style="padding: 20px 28px 28px 28px;">
            <p style="text-align: center; color: #4B5563; margin: 0 0 24px 0; font-size: 14px;">Are you sure you want to save this?</p>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-outline" data-action="close-save-confirm" style="flex: 1; justify-content: center;">
                    <i class="fas fa-times" style="margin-right: 6px;"></i> No
                </button>
                <button class="btn btn-primary" id="saveConfirmBtn" data-action="confirm-save-spot" style="flex: 1; justify-content: center;">
                    <i class="fas fa-check" id="confirmBtnIcon" style="margin-right: 6px;"></i>
                    <i class="fas fa-circle-notch fa-spin" id="confirmBtnSpinner" style="display:none; margin-right:6px;"></i>
                    <span id="confirmBtnLabel">Yes, Save</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal" id="approveConfirmModal" style="z-index: 10003;">
    <div class="modal-content" style="max-width: 440px; border-radius: 16px; overflow: hidden;">
        <div style="background: #ECFDF5; padding: 28px 28px 16px 28px; text-align: center;">
            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #10B981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; box-shadow: 0 4px 14px rgba(16,185,129,0.35);">
                <i class="fas fa-check" style="color: white; font-size: 24px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #065F46;">Approve Tourist Spot</h3>
        </div>
        <div style="padding: 20px 28px 28px 28px;">
            <p style="text-align: center; color: #4B5563; margin: 0 0 24px 0; font-size: 14px; line-height: 1.6;">
                Are you sure you want to approve this tourist spot? It will be made visible to tourists.
            </p>
            <input type="hidden" id="approveSpotId" value="">
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-outline" id="cancelApproveBtn" style="flex: 1; justify-content: center;">
                    <i class="fas fa-times" style="margin-right: 6px;"></i> No
                </button>
                <button class="btn btn-primary" id="confirmApproveBtn" style="flex: 1; justify-content: center; background: linear-gradient(135deg, #10B981, #059669); border-color: #10B981;">
                    <i class="fas fa-check" id="approveBtnIcon" style="margin-right: 6px;"></i>
                    <i class="fas fa-circle-notch fa-spin" id="approveBtnSpinner" style="display:none; margin-right:6px;"></i>
                    <span id="approveBtnLabel">Yes, Approve</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Decline Reason Modal -->
<div class="modal" id="declineModal" style="z-index: 10003;">
    <div class="modal-content" style="max-width: 460px; border-radius: 16px; overflow: hidden;">
        <div style="background: #FEF2F2; padding: 24px 28px 16px 28px; text-align: center;">
            <div style="width: 52px; height: 52px; background: #DC2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                <i class="fas fa-times" style="color: white; font-size: 22px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #991B1B;">Decline Submission</h3>
        </div>
        <div style="padding: 20px 28px 28px 28px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">
                Reason for Rejection <span style="color:#DC2626;">*</span>
            </label>
            <textarea id="declineReason" class="sfm-textarea" rows="3" maxlength="500"
                      placeholder="Provide a reason for declining this submission..." 
                      style="width:100%;min-height:80px;"></textarea>
            <div class="sfm-char-count"><span id="declineReasonCount">0</span>/500</div>
            <input type="hidden" id="declineSpotId" value="">
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button class="btn btn-outline" id="cancelDeclineBtn" style="flex: 1; justify-content: center;">
                    <i class="fas fa-times" style="margin-right:6px;"></i> Cancel
                </button>
                <button class="btn btn-primary" id="confirmDeclineBtn" style="flex: 1; justify-content: center; background: #DC2626; border-color: #DC2626;">
                    <i class="fas fa-check" id="declineBtnIcon" style="margin-right:6px;"></i>
                    <i class="fas fa-circle-notch fa-spin" id="declineBtnSpinner" style="display:none;margin-right:6px;"></i>
                    <span id="declineBtnLabel">Submit</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- -- Scripts  -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" defer></script>

<script>
    window.touristSpotsData = [];
    window.municipalitiesData = [];
    window.userRole = 'picto';
    window.currentUserName = '<?= htmlspecialchars($_SESSION["user_name"] ?? "") ?>';
</script>
<script src="../../scripts/la-union-boundaries.js?v=<?= time() ?>"></script>
<script src="../../scripts/functions/LUPTO/map-view-api.js?v=<?= time() ?>"></script>

<script type="module">
import { initializeAll } from '../../scripts/functions/LUPTO/tourist-spots-api.js?v=<?= time() ?>';

initializeAll();
</script>

<!-- Multi-category filter helpers -->
<script>
function getSelectedCats() {
    return Array.from(document.querySelectorAll('.cat-filter-chk:checked')).map(c => c.value);
}

function onCatFilterChange() {
    const selected = getSelectedCats();
    const label = document.getElementById('catFilterLabel');
    if (selected.length === 0) {
        label.textContent = 'All Categories';
    } else if (selected.length === 1) {
        label.textContent = selected[0];
    } else {
        label.textContent = selected.length + ' selected';
    }
    const btn = document.getElementById('catFilterBtn');
    btn.style.borderColor = selected.length ? '#2563EB' : '';
    btn.style.color       = selected.length ? '#2563EB' : '';
    document.getElementById('searchInput')?.dispatchEvent(new Event('input'));
}

function clearCatFilter() {
    document.querySelectorAll('.cat-filter-chk').forEach(c => c.checked = false);
    onCatFilterChange();
}

function toggleCatDropdown(e) {
    e.stopPropagation();
    const dd      = document.getElementById('catFilterDropdown');
    const chevron = document.getElementById('catChevron');
    const open    = dd.style.display === 'block';
    dd.style.display = open ? 'none' : 'block';
    chevron.style.transform = open ? '' : 'rotate(180deg)';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#catFilterBtn') && !e.target.closest('#catFilterDropdown')) {
        const dd      = document.getElementById('catFilterDropdown');
        const chevron = document.getElementById('catChevron');
        if (dd) dd.style.display = 'none';
        if (chevron) chevron.style.transform = '';
    }
});
</script>

<?php
$pageContent = ob_get_clean();
if (is_ajax_request()) {
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    echo $pageContent;
    exit;
}
include '../../components/sections.php';
