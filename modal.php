<!-------------ASSETS-------------->
<!-- Modal -->
<!-- Add New Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1" aria-labelledby="addAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- The form for adding a new asset -->
            <form method="POST" enctype="multipart/form-data" id="addAssetForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addAssetModalLabel">
                        <i class="bi bi-box-seam me-2"></i>Add New Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-4">

                        <!-- Asset Image Upload -->
                        <div class="col-md-5">
                            <label class="form-label">Asset Image*</label>
                            <div id="assetImagePreviewContainer">
                                <img id="assetImagePreview" src="#" alt="Asset Image Preview" />
                                <div id="assetImagePlaceholder">
                                    <i class="bi bi-image fs-1"></i>
                                    <p class="mb-0">Image Preview</p>
                                </div>
                            </div>
                            <div class="file-upload-wrapper mt-2">
                                <label for="assetImageInput" class="file-upload-label">
                                    <i class="bi bi-upload me-2"></i> <span>Choose or Drop Image</span>
                                </label>
                                <input type="file" name="AssetImage" class="form-control" accept="image/*" id="assetImageInput" required>
                            </div>
                        </div>

                        <!-- Asset Details -->
                        <div class="col-md-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Asset Tag Number*</label>
                                    <input type="text" name="AssetTagNumber" id="AddAssetTagNumber" class="form-control" required placeholder="e.g., COMP-00123">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Asset Type*</label>
                                    <select name="FK_AssetType" class="form-select" required>
                                        <option value="" disabled selected>-- Select Asset Type --</option>
                                        <?php
                                        $query = "SELECT PK_AssetType, AssetTypeName FROM AssetType where Category = 0 ORDER BY AssetTypeName ASC";
                                        $result = mysqli_query($conn, $query);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo '<option value="' . $row['PK_AssetType'] . '">' . htmlspecialchars($row['AssetTypeName']) . '</option>';
                                        }
                                        ?>

                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Asset's Condition*</label>
                                    <select name="condition" class="form-select" required>
                                        <!-- Condition: 1=New, 2=Good, 3=Used, 4=Repaired, 5=Damaged, 6=Under Repair -->
                                        <option value="" disabled selected>-- Select Condition --</option>
                                        <option value="1">New</option>
                                        <option value="2">Good</option>
                                        <option value="3">Used</option>
                                        <option value="4">Repaired</option>
                                        <option value="5">Damaged</option>
                                        <option value="6">Under Repair</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- More Asset Details -->
                        <div class="col-md-6">
                            <label class="form-label">Brand/Manufacturer*</label>
                            <input type="text" name="BrandManufacturer" class="form-control" required placeholder="e.g., Dell">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Model*</label>
                            <input type="text" name="Model" class="form-control" required placeholder="e.g., XPS 15">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="SerialNumber" class="form-control" required placeholder="e.g., ABC123XYZ">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="PurchaseDate" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purchase Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="PurchasePrice" required class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Warranty Expiry Date</label>
                            <input type="date" name="WarrantyExpiryDate" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Supplier/Vendor*</label>
                            <input type="text" name="SupplierVendor" class="form-control" required placeholder="e.g., ABC Office Supplies">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Upload Receipt <span class="text-muted">(Optional)</span><br>
                                <small class="text-danger fst-italic">Note: Once uploaded, it can't be changed</small>
                            </label>
                            <input type="file" name="AssetReceipt" class="form-control"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.odt"
                                id="assetReceiptInput">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="Description" class="form-control" rows="2" placeholder="Add any relevant notes here..." required></textarea>
                        </div>

                        <!-- Location Section -->
                        <div class="col-12">
                            <label class="form-label">Location*</label>
                            <p class="text-muted small mt-0">Search for an address or drag the pin on the map.</p>
                            <div class="position-relative">
                                <input type="text" name="address" id="address" class="form-control" autocomplete="off" placeholder="Search for a location...">
                                <div id="suggestions" class="autocomplete-suggestions"></div>
                            </div>
                            <div id="map" class="mt-3"></div>
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="add">
                        <i class="bi bi-check-circle-fill me-1"></i>Save Asset
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- View Asset Modal -->

<!--
<div class="modal fade" id="viewAssetModal" tabindex="-1" aria-labelledby="viewAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAssetModalLabel">View Asset Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Asset Tag Number</label>
                    <p id="viewAssetTagNumber" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Asset Type</label>
                    <p id="viewAssetType" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Condition</label>
                    <p id="viewCondition" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Brand/Manufacturer</label>
                    <p id="viewBrandManufacturer" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Model</label>
                    <p id="viewModel" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Serial Number</label>
                    <p id="viewSerialNumber" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Purchased Date</label>
                    <p id="viewPurchaseDate" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Warranty Expiry Date</label>
                    <p id="viewWarrantyExpiryDate" class="form-control-plaintext"></p>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Description</label>
                    <p id="viewDescription" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Purchase Price</label>
                    <p id="viewPurchasePrice" class="form-control-plaintext"></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Supplier/Vendor</label>
                    <p id="viewSupplierVendor" class="form-control-plaintext"></p>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Receipt</label>
                    <p id="viewReceipt" class="form-control-plaintext">
                        <a href="#" id="downloadReceiptLink" class="btn btn-sm btn-outline-primary" target="_blank" style="display: none;">
                            <i class="bi bi-download me-1"></i>Download Receipt
                        </a>
                    </p>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
                                    -->


<!-- View Asset Modal -->
<div class="modal fade" id="viewAssetModal" tabindex="-1" aria-labelledby="viewAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="viewAssetModalLabel">
                    <i class="bi bi-file-earmark-text me-2"></i>Asset Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row gy-4">
                    <!-- Right Column: Details (EXPANDED TO FULL WIDTH) -->
                    <div class="col-lg-12">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="view-label">Asset Tag Number</label>
                                <p id="viewAssetTagNumber" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Asset Type</label>
                                <p id="viewAssetType" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Condition</label>
                                <p id="viewCondition" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Brand/Manufacturer</label>
                                <p id="viewBrandManufacturer" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Model</label>
                                <p id="viewModel" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Serial Number</label>
                                <p id="viewSerialNumber" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Purchase Date</label>
                                <p id="viewPurchaseDate" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Warranty Expiry</label>
                                <p id="viewWarrantyExpiryDate" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Purchase Price</label>
                                <p id="viewPurchasePrice" class="view-data"></p>
                            </div>
                            <div class="col-md-6">
                                <label class="view-label">Supplier/Vendor</label>
                                <p id="viewSupplierVendor" class="view-data"></p>
                            </div>
                            <div class="col-12">
                                <label class="view-label">Description</label>
                                <p id="viewDescription" class="view-data" style="min-height: 50px; align-items: flex-start;"></p>
                            </div>
                            <div class="col-12">
                                <label class="view-label">Receipt</label>
                                <div id="viewReceipt">
                                    <a href="#" id="downloadReceiptLink" class="btn btn-sm btn-outline-primary" target="_blank" style="display: none;">
                                        <i class="bi bi-download me-1"></i>Download Receipt
                                    </a>
                                    <span id="noReceiptText" class="text-muted">No receipt uploaded.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- Edit Asset Modal -->
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- The form for editing an asset -->
            <form method="POST" enctype="multipart/form-data" id="editAssetForm">
                <input type="hidden" name="PK_AssetMaster" id="editAssetId">
                <input type="hidden" name="OldImage" id="editOldImage">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editAssetModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-4">

                        <!-- Asset Image Upload -->
                        <div class="col-md-5">
                            <label class="form-label">Asset Image</label>
                            <div id="editAssetImagePreviewContainer" class="image-preview-container">
                                <img id="editAssetImagePreview" src="#" alt="Asset Image Preview" class="image-preview" />
                                <div id="editAssetImagePlaceholder" class="image-placeholder">
                                    <i class="bi bi-image fs-1"></i>
                                    <p class="mb-0">No Image</p>
                                </div>
                            </div>
                            <div class="file-upload-wrapper mt-2">
                                <label class="file-upload-label" for="editAssetImageInput">
                                    <i class="bi bi-upload me-2"></i> <span>Change Image</span>
                                </label>
                                <input type="file" name="AssetImage" class="form-control" accept="image/*" id="editAssetImageInput">
                            </div>
                        </div>

                        <!-- Asset Details -->
                        <div class="col-md-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Asset Tag Number*</label>
                                    <input type="text" name="AssetTagNumber" id="editAssetTagNumber" class="form-control" required placeholder="e.g., COMP-00123">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Asset Type*</label>
                                    <select name="FK_AssetType" id="editAssetType" class="form-select" required>
                                        <option value="" disabled>-- Select Asset Type --</option>
                                        <?php
                                        $query = "SELECT PK_AssetType, AssetTypeName FROM AssetType where Category = 0 ORDER BY AssetTypeName ASC";
                                        $result = mysqli_query($conn, $query);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo '<option value="' . $row['PK_AssetType'] . '">' . htmlspecialchars($row['AssetTypeName']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Asset's Condition*</label>
                                    <select name="condition" id="editCondition" class="form-select" required>
                                        <option value="" disabled>-- Select Condition --</option>
                                        <option value="1">New</option>
                                        <option value="2">Good</option>
                                        <option value="3">Used</option>
                                        <option value="4">Repaired</option>
                                        <option value="5">Damaged</option>
                                        <option value="6">Under Repair</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- More Asset Details -->
                        <div class="col-md-6">
                            <label class="form-label">Brand/Manufacturer*</label>
                            <input type="text" name="BrandManufacturer" id="editBrandManufacturer" class="form-control" required placeholder="e.g., Dell">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Model*</label>
                            <input type="text" name="Model" id="editModel" class="form-control" required placeholder="e.g., XPS 15">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="SerialNumber" id="editSerialNumber" class="form-control" placeholder="e.g., ABC123XYZ">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="PurchaseDate" id="editPurchaseDate" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purchase Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="PurchasePrice" id="editPurchasePrice" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Warranty Expiry Date</label>
                            <input type="date" name="WarrantyExpiryDate" id="editWarrantyExpiryDate" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Supplier/Vendor*</label>
                            <input type="text" name="SupplierVendor" id="editSupplierVendor" class="form-control" required placeholder="e.g., ABC Office Supplies">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="Description" id="editDescription" class="form-control" rows="2" placeholder="Add any relevant notes here..."></textarea>
                        </div>

                        <!-- Location Section -->
                        <div class="col-12">
                            <label class="form-label">Location*</label>
                            <p class="text-muted small mt-0">Search for an address or drag the pin on the map.</p>
                            <div class="position-relative">
                                <input type="text" name="address" id="edit-address" class="form-control" autocomplete="off" placeholder="Search for a location...">
                                <div id="edit-suggestions" class="autocomplete-suggestions"></div>
                            </div>
                            <div id="edit-map" class="map-container mt-3"></div>
                            <input type="hidden" name="latitude" id="edit-latitude">
                            <input type="hidden" name="longitude" id="edit-longitude">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="Update">
                        <i class="bi bi-check-circle-fill me-1"></i>Update Asset
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->


<!-- Delete/Archive Asset Modal -->
<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-labelledby="deleteAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="PK_AssetMaster" id="deleteAssetId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="deleteAssetModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Archival
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="fs-5">Are you sure you want to archive this asset?</p>

                    <div class="card bg-light text-start p-3 my-3">
                        <div class="mb-2">
                            <label class="view-label">Asset Tag Number</label>
                            <p id="deleteAssetTagNumber" class="view-data mb-0"></p>
                        </div>
                        <div>
                            <label class="view-label">Model</label>
                            <p id="deleteAssetModel" class="view-data mb-0"></p>
                        </div>
                    </div>

                    <div class="text-start mb-3">
                        <label for="deleteRemarks" class="form-label">Reason for Archival*</label>
                        <textarea class="form-control" id="deleteRemarks" name="DeleteRemarks" rows="3" placeholder="Please provide a clear reason..." required></textarea>
                    </div>
                    <small class="text-muted">This action can be reversed from the archive section later.</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" name="Delete">
                        <i class="bi bi-archive-fill me-1"></i>Yes, Archive Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!--
<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-labelledby="deleteAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm">
            <form method="POST">
                <input type="hidden" name="PK_AssetMaster" id="deleteAssetId">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title" id="deleteAssetModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                        Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center fs-6">
                    <p class="mb-0">Are you sure you want to <strong>archive</strong> this asset?</p>
                    <small class="text-muted">This action can be reversed from the archive section.</small>
                    <br><br>
                    <div class="mb-3">
                        <textarea class="form-control" id="deleteRemarks" name="DeleteRemarks" rows="3" placeholder="Enter reason for deletion..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1 text-dark"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="Delete">
                        <i class="bi bi-archive-fill me-1 text-white"></i>Move to Archived
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
                                    -->

<!-- Assign Asset Modal -->
<div class="modal fade" id="assignAssetModal" tabindex="-1" aria-labelledby="assignAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="assignForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignAssetModalLabel"><i class="bi bi-person-check me-2"></i>Assign Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="FK_AssetMaster" id="assignAssetId">

                    <div class="row g-4">
                        <!-- Left Column: Asset Info & Image -->
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="view-label">Asset Tag Number</label>
                                <p id="assignAssetTagNumber" class="view-data"></p>
                            </div>
                            <div class="mb-3">
                                <label class="view-label">Asset Model</label>
                                <p id="assignAssetModel" class="view-data"></p>
                            </div>
                            <div>
                                <label class="form-label">Upload Turn-over Image*</label>
                                <div id="assignImagePreviewContainer" class="image-preview-container mb-2">
                                    <img id="assignAssetImagePreview" src="#" alt="Image Preview" class="image-preview" style="display: none;" />
                                    <div id="assignImagePlaceholder" class="image-placeholder">
                                        <i class="bi bi-camera fs-1"></i>
                                        <p class="mb-0">Image Preview</p>
                                    </div>
                                </div>
                                <div class="file-upload-wrapper">
                                    <label for="assignAssetImageInput" class="file-upload-label">
                                        <i class="bi bi-upload me-2"></i> <span>Choose Image</span>
                                    </label>
                                    <input type="file" name="AssetImage" id="assignAssetImageInput" class="form-control" accept="image/*" required>
                                </div>
                            </div>
                        </div>
                        <!-- Right Column: Assignment Details -->
                        <div class="col-md-7">
                            <div class="row g-3">
                                <div class="col-12 position-relative">
                                    <label class="form-label">Assign To*</label>
                                    <div class="input-group">
                                        <input type="text" name="AssignedToName" id="assignToText" class="form-control" placeholder="Search for employee..." autocomplete="off" readonly required>
                                        <button class="btn btn-outline-secondary" type="button" id="assignToInput">
                                            <i class="bi bi-person-plus-fill"></i> Select
                                        </button>
                                    </div>
                                    <input type="hidden" id="assignToId" name="AssignedTo" required>
                                    <div id="assignSuggestions" class="autocomplete-suggestions"></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Date Assigned*</label>
                                    <input type="date" name="DateAcquired" class="form-control" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Condition upon Assigning*</label>
                                    <select name="Conditions" class="form-select" required>
                                        <option value="" disabled selected>-- Select Condition --</option>
                                        <option value="New">New</option>
                                        <option value="Good">Good</option>
                                        <option value="Repaired">Repaired</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Location</label>
                                    <select name="Location" class="form-select" required>
                                        <option value="" disabled selected>Select Location</option>
                                        <option value="FTG">FTG</option>
                                        <option value="Kalantiaw">Kalantiaw</option>
                                        <option value="CMCL">CMCL</option>
                                        <option value="QC">QC</option>
                                    </select>
                                </div>


                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="Remarks" class="form-control" rows="3" placeholder="Add any notes about the assignment..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="assign">
                        <i class="bi bi-check-circle-fill me-1"></i> Assign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Employee Select Modal -->
<div class="modal fade" id="employeeSelectModal" tabindex="-1" aria-labelledby="employeeSelectModalLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1080 !important; ">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="employeeSelectModalLabel"><i class="bi bi-people-fill me-2"></i>Select Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="search-input-group mb-3">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="employeeSearch" class="form-control" placeholder="Search by ID, name, or department...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="employeeTable">
                        <thead class="table-light">
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $empQuery = "SELECT PK_Employees, EmployeeID, Name, Department FROM Employees WHERE Status = 'Active' ORDER BY Name ASC";
                            $empResult = mysqli_query($conn, $empQuery);
                            while ($emp = mysqli_fetch_assoc($empResult)) {
                                echo '<tr data-id="' . $emp['PK_Employees'] . '" data-name="' . htmlspecialchars($emp['EmployeeID'] . ' - ' . $emp['Name']) . '">';
                                echo '<td>' . htmlspecialchars($emp['EmployeeID']) . '</td>';
                                echo '<td>' . htmlspecialchars($emp['Name']) . '</td>';
                                echo '<td>' . htmlspecialchars($emp['Department']) . '</td>';
                                echo '<td><button type="button" class="btn btn-sm btn-primary select-employee-btn">Select</button></td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Unassign Asset Modal -->
<div class="modal fade" id="unassignAssetModal" tabindex="-1" aria-labelledby="unassignAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="UnassignForm">
                <input type="hidden" name="FK_AssetMaster" id="unassignAssetId">
                <input type="hidden" name="FK_AssetInventory" id="unassignPK_AssetInventory">

                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="unassignAssetModalLabel">
                        <i class="bi bi-box-arrow-down me-2"></i>Unassign Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Left Column: Current Assignment Info -->
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="view-label">Asset Tag Number</label>
                                <p id="unassignAssetTagNumber" class="view-data"></p>
                            </div>
                            <div class="mb-3">
                                <label class="view-label">Assigned To</label>
                                <p id="unassignAssignedTo" class="view-data"></p>
                                <input type="hidden" id="unassignToId" name="unassignToId">
                            </div>
                            <div class="mb-3">
                                <label class="view-label">Turn-over Image</label>
                                <div class="image-preview-container">
                                    <img id="unassignImagePreview" src="#" alt="Asset Image Preview" class="image-preview" />
                                    <div id="unassignImagePlaceholder" class="image-placeholder" style="display: none;">
                                        <i class="bi bi-image-alt fs-1"></i>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Unassignment Details -->
                        <div class="col-md-7">
                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="view-label">Location</label>
                                    <p id="unassignLocation" class="view-data"></p>
                                </div>

                                <div class="col-12">
                                    <label class="view-label">Date Acquired</label>
                                    <p id="unassignDateAcquired" class="view-data"></p>
                                </div>

                                <div class="col-12">
                                    <label class="view-label">Condition</label>
                                    <p id="unassignCondition" class="view-data"></p>
                                </div>

                                <div class="col-12">
                                    <label class="view-label">Assigned Remarks</label>
                                    <p id="unassignRemarks" class="view-data"></p>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Reason for Unassignment*</label>
                                    <select name="UnassignReason" id="unassignReasonSelect" class="form-select" required>
                                        <option value="">-- Select a reason --</option>
                                        <option value="Employee Resigned">Employee Resigned</option>
                                        <option value="Returned for Upgrade">Returned for Upgrade</option>
                                        <option value="Asset Malfunctioning">Asset Malfunctioning</option>
                                        <option value="Asset No Longer Needed">Asset No Longer Needed</option>
                                        <option value="Other">Other...</option>
                                    </select>
                                </div>

                                <div class="col-12" id="otherReasonGroup" style="display: none;">
                                    <label class="form-label">Specify Other Reason</label>
                                    <input type="text" name="OtherReason" id="OtherReason" class="form-control" placeholder="Please specify the reason">
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="unassign">
                        <i class="bi bi-check-circle-fill me-1"></i>Confirm Unassignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-------------------------------------------------------------------------------------------------->


<!-------------Employee-------------->

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEmployeeModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>


                <div class="modal-body row g-3">
                    <input type="hidden" name="PK_Employees" id="editEmployeeId">

                    <div class="col-md-6">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control" name="EmployeeID" id="editEmployeeID" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="Name" id="editName" required>
                    </div>

                    <!--
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="Department" id="editDepartment">
                    </div>
                    -->

                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="Department" id="editDepartment" required>
                            <option value="" disabled selected>Select Department</option>
                            <option value="HR">HR</option>
                            <option value="IT">IT</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Operations">Operations</option>
                            <option value="Canvasser">Canvasser</option>
                            <!-- Add more options as needed -->
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="Position" id="editPosition">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="Email" id="editEmail">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="PhoneNumber" id="editPhone">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date Hired</label>
                        <input type="date" class="form-control" name="DateHired" id="editDateHired">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="Status" id="editStatus" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-dark" name="editEmployee">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Add Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>


                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control" name="EmployeeID" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="Name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="Department" required>
                            <option value="" disabled selected>Select Department</option>
                            <option value="HR">HR</option>
                            <option value="IT">IT</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Operations">Operations</option>
                            <option value="Canvasser">Canvasser</option>
                            <!-- Add more options as needed -->
                        </select>
                    </div>


                    <div class="col-md-6">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="Position">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="Email">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="PhoneNumber">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date Hired</label>
                        <input type="date" class="form-control" name="DateHired">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="Status" required>
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-dark" name="addEmployee">
                        <i class="bi bi-person-check-fill me-1"></i>Add Employee
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!---Asset Types ------------------>
<!-- Edit Asset Type Modal -->
<div class="modal fade" id="editAssetTypeModal" tabindex="-1" aria-labelledby="editAssetTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="editAssetTypeModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Asset Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="PK_AssetType" id="editAssetTypeId">
                    <div class="mb-3">
                        <label for="editAssetTypeName" class="form-label">Asset Type Name</label>
                        <input type="text" class="form-control" id="editAssetTypeName" name="AssetTypeName" placeholder="Enter asset type name" required>
                    </div>
                    <div class="mb-3">
                        <label for="AssetTypeCategory" class="form-label">Category Type</label>
                        <select class="form-select" id="EditAssetTypeCategory" name="AssetTypeCategory" required>
                            <option value="0">Asset</option>
                            <option value="1">General Asset</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" name="update_asset_type" class="btn btn-dark">
                        <i class="bi bi-save2 me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="addCategoryModalLabel">
                        <i class="bi bi-folder-plus me-2"></i>Add Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="AssetTypeName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="AssetTypeName" name="AssetTypeName" placeholder="Enter category name" required>
                    </div>


                    <div class="mb-3">
                        <label for="AssetTypeCategory" class="form-label">Category Type</label>
                        <select class="form-select" id="AssetTypeCategory" name="AssetTypeCategory" required>
                            <option value="0">Asset</option>
                            <option value="1">General Asset</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-dark" name="add_asset_type">
                        <i class="bi bi-plus-circle me-1"></i>Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAssetTypeModal" tabindex="-1" aria-labelledby="deleteAssetTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAssetTypeModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="delete_id" id="deleteAssetTypeId">
                    <p>Are you sure you want to delete the category <strong id="deleteAssetTypeName"></strong>?</p>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" name="delete_asset_type" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add General Asset Modal -->
<div class="modal fade" id="addGenAssetModal" tabindex="-1" aria-labelledby="addGenAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="addGeneralAssetForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addGenAssetModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add General Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Left Column: Image -->
                        <div class="col-md-5">
                            <label class="form-label">Asset Image</label>
                            <div id="addGenImagePreviewContainer" class="image-preview-container">
                                <img id="addGenImagePreview" src="#" alt="Asset Image Preview" class="image-preview" style="display: none;" />
                                <div id="addGenImagePlaceholder" class="image-placeholder">
                                    <i class="bi bi-image fs-1"></i>
                                    <p class="mb-0">Image Preview</p>
                                </div>
                            </div>
                            <div class="file-upload-wrapper mt-2">
                                <label class="file-upload-label" for="addGenImageInput">
                                    <i class="bi bi-upload me-2"></i> <span>Choose Image</span>
                                </label>
                                <input type="file" name="Image" class="form-control" accept="image/*" id="addGenImageInput" required>
                            </div>
                        </div>

                        <!-- Right Column: Details -->
                        <div class="col-md-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="addGenName" class="form-label">Asset Name*</label>
                                    <input type="text" name="Name" id="addGenName" class="form-control" required placeholder="e.g., Office Chairs">
                                </div>
                                <div class="col-sm-6">
                                    <label for="addGenAssetType" class="form-label">Asset Type*</label>
                                    <select name="FK_AssetType" id="addGenAssetType" class="form-select" required>
                                        <!-- This would be populated by PHP -->
                                        <option value="" disabled selected>Select type...</option>
                                       
                                        <?php
                                        $sql = "SELECT PK_AssetType, AssetTypeName FROM AssetType ORDER BY AssetTypeName ASC";
                                        $resultAssetTypes = mysqli_query($conn, $sql);
                                        if ($resultAssetTypes && mysqli_num_rows($resultAssetTypes) > 0) {
                                            while ($type = mysqli_fetch_assoc($resultAssetTypes)) {
                                                echo '<option value="' . $type['PK_AssetType'] . '">' . htmlspecialchars($type['AssetTypeName']) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="">No asset types available</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label for="addQuantity" class="form-label">Quantity*</label>
                                    <input type="number" name="Quantity" id="addQuantity" class="form-control" required placeholder="e.g., 12">
                                </div>
                            </div>
                        </div>

                        <!-- Full Width Details -->
                        <div class="col-md-6">
                            <label for="addLocation" class="form-label">Location*</label>
                            <input type="text" name="Location" id="addLocation" class="form-control" required placeholder="e.g., Main Office">
                        </div>

                        <div class="col-md-6">
                            <label for="addPrice" class="form-label">Purchase Price (per item)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="PurchasePrice" id="addPrice" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="addGenDescription" class="form-label">Description</label>
                            <textarea name="Descriptions" id="addGenDescription" class="form-control" rows="2" placeholder="Add any relevant notes here..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" name="add_general_asset">
                        <i class="bi bi-check-circle-fill me-1"></i> Add Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Edit General Asset Modal -->
<div class="modal fade" id="editGeneralAssetModal" tabindex="-1" aria-labelledby="editGeneralAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="GeneralAssetMaster" id="editGenAssetId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editGeneralAssetModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit General Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Left Column: Image -->
                        <div class="col-md-5">
                            <label class="form-label">Asset Image</label>
                            <div id="editGenImagePreviewContainer" class="image-preview-container">
                                <img id="currentImage" src="#" alt="Asset Image Preview" class="image-preview" />
                                <div id="editGenImagePlaceholder" class="image-placeholder">
                                    <i class="bi bi-image fs-1"></i>
                                    <p class="mb-0">No Image</p>
                                </div>
                            </div>
                            <div class="file-upload-wrapper mt-2">
                                <label class="file-upload-label" for="editGenImageInput">
                                    <i class="bi bi-upload me-2"></i> <span>Change Image</span>
                                </label>
                                <input type="file" name="Image" class="form-control" accept="image/*" id="editGenImageInput">
                            </div>
                        </div>

                        <!-- Right Column: Details -->
                        <div class="col-md-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="editGenName" class="form-label">Asset Name*</label>
                                    <input type="text" name="Name" id="editGenName" class="form-control" required>
                                </div>
                                <div class="col-sm-6">
                                    <label for="editGenAssetType" class="form-label">Asset Type*</label>
                                    <select name="FK_AssetType" id="editGenAssetType" class="form-select" required>
                                        <?php
                                        $sql = "SELECT PK_AssetType, AssetTypeName FROM AssetType ORDER BY AssetTypeName ASC";
                                        $resultAssetTypes = mysqli_query($conn, $sql);
                                        if ($resultAssetTypes && mysqli_num_rows($resultAssetTypes) > 0) {
                                            while ($type = mysqli_fetch_assoc($resultAssetTypes)) {
                                                echo '<option value="' . $type['PK_AssetType'] . '">' . htmlspecialchars($type['AssetTypeName']) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="">No asset types available</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label for="editQuantity" class="form-label">Quantity*</label>
                                    <input type="number" name="Quantity" id="editQuantity" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <!-- Full Width Details -->
                        <div class="col-md-6">
                            <label for="editLocation" class="form-label">Location*</label>
                            <input type="text" name="Location" id="editLocation" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label for="editPrice" class="form-label">Purchase Price (per item)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="PurchasePrice" id="editPrice" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="editGenDescription" class="form-label">Description</label>
                            <textarea name="Descriptions" id="editGenDescription" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" name="EditGenAsset">
                        <i class="bi bi-check-circle-fill me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Delete General Asset Modal -->

<!--
<div class="modal fade" id="deleteGeneralAssetModal" tabindex="-1" aria-labelledby="deleteGeneralAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="deleteGeneralAssetForm">
            <input type="hidden" name="GeneralAssetMaster" id="deleteGenAssetId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteGeneralAssetModalLabel">
                        <i class="bi bi-trash3-fill me-2 text-danger"></i>Archive Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this asset: <strong id="deleteGenAssetName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger" name="DeleteGenAsset">
                        <i class="bi bi-check-circle me-1"></i> Yes
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
                                    -->

<!-- Delete General Asset Modal -->
<div class="modal fade" id="deleteGeneralAssetModal" tabindex="-1" aria-labelledby="deleteGeneralAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" id="deleteGeneralAssetForm">
                <input type="hidden" name="GeneralAssetMaster" id="deleteGenAssetId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="deleteGeneralAssetModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Archival
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="fs-5">Are you sure you want to archive this asset?</p>

                    <div class="card bg-light text-start p-3 my-3">
                        <label class="view-label">Asset Name</label>
                        <p id="deleteGenAssetName" class="view-data mb-0"></p>
                    </div>

                    <div class="text-start mb-3">
                        <label for="deleteGenAssetRemarks" class="form-label">Reason for Archival*</label>
                        <textarea class="form-control" id="deleteGenAssetRemarks" name="DeleteGenAssetRemarks" rows="3" placeholder="Please provide a clear reason..." required></textarea>
                    </div>
                    <small class="text-muted">This action can be reversed from the archive section later.</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" name="DeleteGenAsset">
                        <i class="bi bi-archive-fill me-1"></i>Yes, Archive Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Employee Selection Modal -->
<div class="modal fade" id="employeeSelectModalOutbound" tabindex="-1" aria-labelledby="employeeSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeSelectModalLabel">Select Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table id="employeeTables" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Choose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT PK_Employees, Name, Department, Position, Email FROM Employees WHERE Status = 'Active' ORDER BY Name ASC";
                        $result = mysqli_query($conn, $sql);
                        if ($result && mysqli_num_rows($result) > 0):
                            while ($emp = mysqli_fetch_assoc($result)):
                        ?>
                                <tr>
                                    <td><?= htmlspecialchars($emp['Name']) ?></td>
                                    <td><?= htmlspecialchars($emp['Department']) ?></td>
                                    <td><?= htmlspecialchars($emp['Position']) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary select-employee-btn"
                                            data-employee-id="<?= $emp['PK_Employees'] ?>"
                                            data-employee-name="<?= htmlspecialchars($emp['Name'], ENT_QUOTES) ?>"
                                            data-bs-dismiss="modal">
                                            Select
                                        </button>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else:
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Selection Modal -->
<div class="modal fade" id="productSelectModal" tabindex="-1" aria-labelledby="productSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productSelectModalLabel">Select Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productModalContent">

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approveAssetModal" tabindex="-1" aria-labelledby="approveAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="approveAssetForm">
                <input type="hidden" name="PK_OutboundAssets" id="approveAssetId">

                <div class="modal-header">
                    <h5 class="modal-title text-success" id="approveAssetModalLabel">
                        <i class="bi bi-check-circle-fill me-2"></i>Confirm Approval
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to approve this request?</p>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" id="showApproveModalBtn" class="btn btn-success" name="Approve">
                        <i class="bi bi-check2-circle me-1"></i>Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="disapproveAssetModal" tabindex="-1" aria-labelledby="disapproveAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="disapproveAssetForm">
                <input type="hidden" name="PK_OutboundAssets" id="disapproveAssetId">

                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="disapproveAssetModalLabel">
                        <i class="bi bi-x-octagon-fill me-2"></i>Confirm Rejection
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to reject this request?</p>
                    <br>
                    <div class="mb-3">
                        <textarea class="form-control" id="rejectionReason" name="RejectionReason" rows="4" placeholder="Enter your reason here..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" name="Disapprove">
                        <i class="bi bi-x-lg me-1"></i>Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="returnModal" data-bs-backdrop="static"
    data-bs-keyboard="false" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="returnForm">
                <input type="hidden" name="PK_OutboundAssets" id="returnAssetId">

                <input type="hidden" name="returnedData" id="returnedData">

                <div class="modal-header">
                    <h5 class="modal-title" id="returnModalLabel">Mark as Returned</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Serial Number</th>
                                <th>Name</th>
                                <th>Asset Type</th>
                                <th>Dispatched</th>
                                <th>Received</th>
                                <th>Enter Quantity</th>
                                <th>Date Returned</th>
                                <th>Returned</th>
                            </tr>
                        </thead>
                        <tbody id="returnModalTableBody"></tbody>
                    </table>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" name="MarkReturned">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Asset History Modal -->
<div class="modal fade" id="historyAssetModal" tabindex="-1" aria-labelledby="historyAssetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-semibold" id="historyAssetModalLabel">
                    <i class="bi bi-clock-history me-2"></i>Asset History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-0">
                <div id="historyContent">
                    <div class="text-center py-3 text-muted">
                        <div class="spinner-border text-secondary me-2" role="status" style="width: 1.5rem; height: 1.5rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading history...
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Repaired Asset Modal -->
<div class="modal fade" id="repairedAssetModal" tabindex="-1" aria-labelledby="repairedAssetModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="repairedAssetForm" enctype="multipart/form-data">
                <input type="hidden" name="AssetID" id="repairedAssetId">

                <div class="modal-header">
                    <h5 class="modal-title" id="repairedAssetModalLabel">Mark Asset as Repaired</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Asset Tag:</label>
                        <div><strong id="repairedAssetTagText"></strong></div>
                    </div>

                    <div class="mb-3">
                        <label for="RepairDate" class="form-label">Date Sent for Repair</label>
                        <input type="date" class="form-control" name="RepairDate" id="RepairDate" required>
                    </div>

                    <div class="mb-3">
                        <label for="RepairDetails" class="form-label">Repair Details</label>
                        <textarea class="form-control" name="RepairDetails" id="RepairDetails" rows="3" required></textarea>
                    </div>

                    <!-- Toggle Switch -->
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="isRepairedSwitch" name="isRepaired">
                        <label class="form-check-label" for="isRepairedSwitch">Is device repaired?</label>
                    </div>

                    <!-- Repaired Fields (initially hidden) -->
                    <div id="repairedFields" style="display: none;">
                        <div class="mb-3">
                            <label for="RepairedDate" class="form-label">Date Repaired</label>
                            <input type="date" class="form-control" name="RepairedDate" id="RepairedDate">
                        </div>

                        <div class="mb-3">
                            <label for="RepairedBy" class="form-label">Repaired Details</label>
                            <textarea class="form-control" name="RepairedBy" id="RepairedBy" rows="2"
                                placeholder="Enter mobile number, shop name, and address"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="RepairValue" class="form-label">Repair Cost (₱)</label>
                            <input type="number" class="form-control" name="RepairValue" id="RepairValue"
                                step="0.01" min="0" placeholder="Enter repair cost">
                        </div>

                        <div class="mb-3">
                            <label for="ServiceOrder" class="form-label">Upload Service Order (Optional)</label>
                            <input type="file" class="form-control" name="ServiceOrder" id="ServiceOrder"
                                accept=".xlsx,.xls,.csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf,image/*">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" name="markRepaired">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Unassignment Approval Details Modal -->
<div class="modal fade" id="unassignApprovalAssetModal" tabindex="-1" aria-labelledby="unassignApprovalAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="unassignApprovalAssetModalLabel">Unassignment Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Request Type</dt>
                    <dd class="col-sm-8">Unassignment</dd>

                    <dt class="col-sm-4">Approval Status</dt>
                    <dd class="col-sm-8">Pending</dd>

                    <dt class="col-sm-4">Employee</dt>
                    <dd class="col-sm-8" id="employeeNameText">--</dd>

                    <dt class="col-sm-4">Asset</dt>
                    <dd class="col-sm-8" id="assetNameText">--</dd>

                    <dt class="col-sm-4">Reason</dt>
                    <dd class="col-sm-8" id="reasonText">--</dd>

                    <dt class="col-sm-4 d-none" id="otherReasonLabel">Other Reason</dt>
                    <dd class="col-sm-8 d-none" id="otherReasonText">--</dd>

                    <dt class="col-sm-4">Submitted On</dt>
                    <dd class="col-sm-8" id="submittedOnText">--</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- View Assignment Approval Details Modal -->

<div class="modal fade" id="assignApprovalAssetModal" tabindex="-1" aria-labelledby="assignApprovalAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uassignApprovalAssetModalModalLabel">Assignment Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Request Type</dt>
                    <dd class="col-sm-8">Assignment</dd>

                    <dt class="col-sm-4">Approval Status</dt>
                    <dd class="col-sm-8">Pending</dd>

                    <dt class="col-sm-4">Employee</dt>
                    <dd class="col-sm-8" id="employeeNameText2">--</dd>

                    <dt class="col-sm-4">Asset</dt>
                    <dd class="col-sm-8" id="assetNameText2">--</dd>

                    <dt class="col-sm-4">Reason</dt>
                    <dd class="col-sm-8" id="reasonText2">--</dd>

                    <dt class="col-sm-4 d-none" id="otherReasonLabel2">Other Reason</dt>
                    <dd class="col-sm-8 d-none" id="otherReasonText2">--</dd>

                    <dt class="col-sm-4">Submitted On</dt>
                    <dd class="col-sm-8" id="submittedOnText2">--</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- View Approval Details Modal -->
<div class="modal fade" id="viewApprovalModal" tabindex="-1" aria-labelledby="viewApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="viewApprovalModalLabel">Asset Approval Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <input type="text" id="viewAssignedTo" class="form-control" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" id="viewLocation" class="form-control" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date Acquired</label>
                    <input type="date" id="viewDateAcquired" class="form-control" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Condition</label>
                    <input type="text" id="viewConditions" class="form-control" readonly>
                </div>

                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea id="viewRemarks" class="form-control" rows="2" readonly></textarea>
                </div>

            </div>

            <div class="col-12 text-center">
                <label class="form-label d-block mb-2 fs-6 fw-semibold">Image</label>
                <div class="border rounded-3 shadow-sm d-inline-block p-2 bg-light">
                    <img id="viewAssetImage"
                        src="#"
                        alt="No image available"
                        class="img-fluid rounded-2"
                        style="max-height: 250px; object-fit: contain; display: none;">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- Approve Assignment Confirmation Modal -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-labelledby="confirmApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="confirmApprovalAssign">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmApproveModalLabel">Confirm Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to approve this? <strong id="modalAssetName"></strong>
                    <input type="hidden" name="approval_id" id="modalApprovalId">
                    <input type="hidden" name="asset_id" id="asset_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" name="submitApproval">Yes, Approve</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="confirmRejectModal" tabindex="-1" aria-labelledby="confirmRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmRejectModalLabel">Confirm Rejection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reject <strong id="rejectAssetName"></strong>?
                    <input type="hidden" name="approval_id" id="rejectApprovalId">
                    <input type="hidden" name="asset_id" id="rejectAssetId">
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label fw-bold text-danger">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectionReason" name="RejectionReason" rows="4" placeholder="Enter your reason here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" name="submitRejection">Yes, Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Unassign Modal -->
<div class="modal fade" id="unassignAssetModalView" tabindex="-1" aria-labelledby="unassignAssetModalViewLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="unassignAssetModalViewLabel">Unassign Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="FK_AssetMaster" id="unassignAssetIdView">

                    <div class="col-md-6">
                        <label class="form-label">Assigned To</label>
                        <input type="text" id="unassignToInputView" name="AssignedToName" class="form-control" readonly>
                        <input type="hidden" id="unassignToIdView" name="unassignToId">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" id="unassignLocationView" name="Location" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date Acquired</label>
                        <input type="date" id="unassignDateAcquiredView" name="DateAcquired" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Condition</label>
                        <input type="text" id="unassignConditionView" name="Conditions" class="form-control" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea id="unassignRemarksView" name="Remarks" class="form-control" rows="3" readonly></textarea>
                    </div>

                    <!--
                    <div class="col-md-12">
                        <label class="form-label">Reason for Unassignment</label>
                        <select name="UnassignReason" id="UnassignReasonView" class="form-select" required>
                            <option value="">-- Select a reason --</option>
                            <option value="Employee Resigned">Employee Resigned</option>
                            <option value="Transferred to Another Department">Transferred to Another Department</option>
                            <option value="Asset No Longer Needed">Asset No Longer Needed</option>
                            <option value="Asset Malfunctioning">Asset Malfunctioning</option>
                            <option value="Returned for Upgrade">Returned for Upgrade</option>
                            <option value="Damaged Beyond Use">Damaged Beyond Use</option>
                            <option value="Swapped with New Asset">Swapped with New Asset</option>
                            <option value="Temporary Unassignment">Temporary Unassignment</option>
                            <option value="Employee on Long Leave">Employee on Long Leave</option>
                            <option value="Other">Other (See Remarks)</option>
                        </select>
                    </div>

                  

                    <div class="col-md-12 mt-2" id="otherReasonGroup" style="display: none;">
                        <label class="form-label">Specify Other Reason</label>
                        <input type="text" name="OtherReason" id="OtherReason" class="form-control" placeholder="Enter other reason">
                    </div>

                        -->



                    <div class="col-12 text-center">
                        <label class="form-label d-block mb-2 fs-6 fw-semibold">Asset Image</label>
                        <div class="border rounded-3 shadow-sm d-inline-block p-2 bg-light">
                            <img id="unassignImagePreviewView"
                                src="#"
                                alt="No image available"
                                class="img-fluid rounded-2"
                                style="max-height: 250px; display: none; object-fit: contain;">
                        </div>
                        <div class="form-text mt-1 text-muted">This image is associated with the asset assignment.</div>
                    </div>


                </div>

            </form>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreAssetModal" tabindex="-1" aria-labelledby="restoreAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="restoreAssetForm">
                <input type="hidden" name="AssetID" id="restoreAssetId">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="restoreAssetModalLabel">
                        <i class="bi bi-arrow-clockwise me-2"></i>Confirm Restore
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p>Are you sure you want to restore this asset?</p>
                    <p class="fw-bold text-danger" id="restoreAssetTagText"></p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" name="confirmRestore">Yes, Restore</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- General Restore Confirmation Modal -->
<div class="modal fade" id="restoreGeneralAssetModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreModalLabel">
                        <i class="bi bi-arrow-clockwise me-2 text-success"></i>Confirm Restore
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to restore <strong id="restoreAssetName"></strong>?
                    <input type="hidden" name="RestoreGenAsset" value="1">
                    <input type="hidden" name="GeneralAssetMaster" id="restoreAssetId2">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Restore
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Received In Transfer Assets -->


<!-- Received In Transfer Assets Modal -->
<div class="modal fade" id="receivedModal" tabindex="-1" aria-labelledby="receivedModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="receivedForm" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="receivedModalLabel"><i class="bi bi-check-circle-fill me-2"></i>Confirm Receipt of Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="transfer_id" id="transferIdInput">
                    <div class="row g-4">
                        <!-- Left Column: Transfer Info -->
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="view-label">Transfer ID</label>
                                <p id="viewTransferId" class="view-data"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Attach Proof of Receipt*</label>
                                <div class="image-preview-container mb-2">
                                    <img id="receivedImagePreview" src="#" alt="Image Preview" class="image-preview" />
                                    <div id="receivedImagePlaceholder" class="image-placeholder">
                                        <i class="bi bi-camera fs-1"></i>
                                        <p class="mb-0">Image Preview</p>
                                    </div>
                                </div>
                                <div class="file-upload-wrapper">
                                    <label for="receivedImageInput" class="file-upload-label">
                                        <i class="bi bi-upload me-2"></i> <span>Choose Image</span>
                                    </label>
                                    <input type="file" class="form-control" name="received_image" id="receivedImageInput" accept="image/*" required>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Form Inputs -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label for="receivedDate" class="form-label">Date Received*</label>
                                <input type="date" class="form-control" name="received_date" id="receivedDate" required>
                            </div>
                            <div class="mb-3">
                                <label for="receivedBy" class="form-label">Received By*</label>
                                <input type="text" class="form-control" name="receivedBy" id="receivedBy" placeholder="Enter full name..." required>
                            </div>
                            <div class="mb-3">
                                <label for="remarksTextarea" class="form-label">Remarks*</label>
                                <textarea class="form-control" name="remarks" id="remarksTextarea" rows="5" placeholder="Enter remarks, e.g., condition of items upon receipt..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success" name="MarkReceived">
                        <i class="bi bi-check-lg me-1"></i>Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Reusable Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Service Order Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Service Order" class="img-fluid rounded border">
            </div>
        </div>
    </div>
</div>