document.querySelectorAll('.btn-view-asset').forEach(button => {
    button.addEventListener('click', function () {
        const asset = JSON.parse(this.getAttribute('data-asset'));

        const conditionLabels = {
            1: "New",
            2: "Good",
            3: "Used",
            4: "Repaired",
            5: "Damaged",
            6: "Under Repair"
        };

        document.getElementById('viewPurchaseDate').textContent = asset.PurchaseDate || '';
        document.getElementById('viewAssetTagNumber').textContent = asset.AssetTagNumber || '';
        document.getElementById('viewCondition').textContent = conditionLabels[asset.Conditions] || '';
        document.getElementById('viewAssetType').textContent = asset.AssetTypeName || '';
        document.getElementById('viewBrandManufacturer').textContent = asset.BrandManufacturer || '';
        document.getElementById('viewModel').textContent = asset.Model || '';
        document.getElementById('viewSerialNumber').textContent = asset.SerialNumber || '';
        document.getElementById('viewWarrantyExpiryDate').textContent = asset.WarrantyExpiryDate || '';
        document.getElementById('viewDescription').textContent = asset.Descriptions || '';
        document.getElementById('viewPurchasePrice').textContent = asset.PurchasePrice && !isNaN(asset.PurchasePrice)
            ? parseFloat(asset.PurchasePrice).toFixed(2)
            : '';

        document.getElementById('viewSupplierVendor').textContent = asset.SupplierVendor || '';

        const receiptFileName = asset.Receipt || '';
        const receiptBasePath = 'image/assetreceipts/';
        const noReceiptText = document.getElementById('noReceiptText');

        const downloadLink = document.getElementById('downloadReceiptLink');
        if (receiptFileName) {
            downloadLink.href = receiptBasePath + receiptFileName;
            downloadLink.style.display = 'inline-block';
            noReceiptText.style.display = 'none'; // Hide the "No receipt" text
        } else {
            downloadLink.style.display = 'none';
            noReceiptText.style.display = 'inline'; // Show the "No receipt" text
        }

    });
});

document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.editAssetBtn');

    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const asset = JSON.parse(this.getAttribute('data-asset'));

            document.getElementById('editCondition').value = asset.Conditions || '';
            document.getElementById('editAssetId').value = asset.PK_AssetMaster || '';
            document.getElementById('editAssetTagNumber').value = asset.AssetTagNumber || '';
            document.getElementById('editAssetType').value = asset.FK_AssetType || '';
            document.getElementById('editBrandManufacturer').value = asset.BrandManufacturer || '';
            document.getElementById('editModel').value = asset.Model || '';
            document.getElementById('editSerialNumber').value = asset.SerialNumber || '';
            document.getElementById('editWarrantyExpiryDate').value = asset.WarrantyExpiryDate || '';
            document.getElementById('editDescription').value = asset.Descriptions || '';
            document.getElementById('editPurchasePrice').value = asset.PurchasePrice || '';
            document.getElementById('editSupplierVendor').value = asset.SupplierVendor || '';
            document.getElementById('editPurchaseDate').value = asset.PurchaseDate || '';
            document.getElementById('edit-longitude').value = asset.longitude || '';
            document.getElementById('edit-latitude').value = asset.latitude || '';

            document.getElementById('editOldImage').value = asset.Image;

            const preview = document.getElementById('editAssetImagePreview');
            if (asset.Image) {
                console.log("Setting image preview to:", 'assetimages/' + asset.Image);
                preview.src = 'image/assetimages/' + asset.Image;
                preview.style.display = 'block';
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }

        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.btn-delete-asset');
    const deleteInput = document.getElementById('deleteAssetId');
    const deleteAssetTagNumber = document.getElementById('deleteAssetTagNumber');
    const deleteAssetModel = document.getElementById('deleteAssetModel');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const deleteAssetTagNumberVal = this.getAttribute('data-assetTag');
            const deleteAssetModelVal = this.getAttribute('data-assetModel');
            deleteInput.value = id;
            deleteAssetTagNumber.textContent = deleteAssetTagNumberVal;
            deleteAssetModel.textContent = deleteAssetModelVal;
        });
    });
});

document.querySelectorAll('.btn-assign-asset').forEach(button => {
    button.addEventListener('click', function () {
        const assetId = this.getAttribute('data-id');
        const assetTagNumber = this.getAttribute('data-assetTag');
        const assetModel = this.getAttribute('data-assetModel');

        document.getElementById('assignAssetId').value = assetId;
        document.getElementById('assignAssetTagNumber').textContent = assetTagNumber || '';
        document.getElementById('assignAssetModel').textContent = assetModel || '';
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const assignToInput = document.getElementById('assignToInput');
    const assignToId = document.getElementById('assignToId');
    const employeeSelectModal = new bootstrap.Modal(document.getElementById('employeeSelectModal'));
    const employeeSearch = document.getElementById('employeeSearch');
    const employeeTableBody = document.querySelector('#employeeTable tbody');

    const assignToText = document.getElementById('assignToText');



    // Open modal on input click
    assignToInput.addEventListener('click', () => {
        employeeSearch.value = '';
        filterEmployees('');
        employeeSelectModal.show();
    });

    // Filter employee rows based on search input
    employeeSearch.addEventListener('input', () => {
        filterEmployees(employeeSearch.value);
    });

    function filterEmployees(searchText) {
        const rows = employeeTableBody.querySelectorAll('tr');
        searchText = searchText.toLowerCase();

        rows.forEach(row => {
            const id = row.children[0].textContent.toLowerCase();
            const name = row.children[1].textContent.toLowerCase();

            if (id.includes(searchText) || name.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Handle click on "Select" button in modal
    employeeTableBody.addEventListener('click', (e) => {
        if (e.target.classList.contains('select-employee-btn')) {
            const row = e.target.closest('tr');
            const id = row.getAttribute('data-id');
            const name = row.getAttribute('data-name');

            assignToText.value = name;
            assignToId.value = id;
            employeeSelectModal.hide();
        }
    });
});

document.querySelectorAll('.btn-unassign-asset').forEach(btn => {
    btn.addEventListener('click', () => {
        const assetId = btn.getAttribute('data-id');
        const invId = btn.getAttribute('data-invID');
        const UnassignAssetTag = btn.getAttribute('data-assetTag');

        document.getElementById('unassignAssetTagNumber').textContent = UnassignAssetTag;
        //document.getElementById('unassignAssignedTo').value = assetId;


        // Set asset ID in modal hidden input
        document.getElementById('unassignAssetId').value = assetId;


        const imgPreview = document.getElementById('unassignImagePreview');

        fetch('tools/load-assignedDetails.php?id=' + encodeURIComponent(assetId) + '&invId=' + encodeURIComponent(invId))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const asset = data.asset;

                    document.getElementById('unassignToId').value = asset.AssignedTo || 0;
                    document.getElementById('unassignLocation').value = asset.Location || '';
                    document.getElementById('unassignDateAcquired').textContent = asset.DateAcquired || '';
                    document.getElementById('unassignCondition').textContent = asset.Conditions || '';
                    document.getElementById('unassignRemarks').textContent = asset.Remarks || '';
                    document.getElementById('unassignPK_AssetInventory').value = asset.PK_AssetInventory || '';
                    document.getElementById('unassignAssignedTo').textContent = asset.AssignedToName;
                    document.getElementById('unassignLocation').textContent = asset.Location;

                    if (asset.Image) {
                        imgPreview.src = 'image/assignimages/' + asset.Image;
                        imgPreview.style.display = 'block';
                    } else {
                        imgPreview.style.display = 'none';
                        imgPreview.src = '#';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert('Failed to fetch asset inventory data.');
            });
    });
});

const assignImageInput = document.getElementById('assignAssetImageInput');
const assignImagePreview = document.getElementById('assignAssetImagePreview');
const assignImagePlaceholder = document.getElementById('assignImagePlaceholder');

assignImageInput.addEventListener('change', function (event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            assignImagePreview.src = e.target.result;
            assignImagePreview.style.display = 'block';
            assignImagePlaceholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
});

document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', () => {
        const userId = btn.getAttribute('data-id');
        const userName = btn.getAttribute('data-name');
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
    });
});

document.querySelectorAll('.btn-edit-employee').forEach(button => {
    button.addEventListener('click', () => {
        const employee = JSON.parse(button.getAttribute('data-employee'));

        document.getElementById('editEmployeeId').value = employee.PK_Employees;
        document.getElementById('editEmployeeID').value = employee.EmployeeID;
        document.getElementById('editName').value = employee.Name;
        document.getElementById('editDepartment').value = employee.Department;
        document.getElementById('editPosition').value = employee.Position;
        document.getElementById('editEmail').value = employee.Email;
        document.getElementById('editPhone').value = employee.PhoneNumber;
        document.getElementById('editDateHired').value = employee.DateHired;
        document.getElementById('editStatus').value = employee.Status;
    });
});

const editModal = document.getElementById('editAssetTypeModal');
editModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    const name = button.getAttribute('data-name');
    const categoryid = button.getAttribute('data-category');

    editModal.querySelector('#editAssetTypeId').value = id;
    editModal.querySelector('#editAssetTypeName').value = name;
    editModal.querySelector('#EditAssetTypeCategory').value = categoryid;

});

document.addEventListener('DOMContentLoaded', function () {
    var deleteModal = document.getElementById('deleteAssetTypeModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-id');
        var assetName = button.getAttribute('data-name');

        document.getElementById('deleteAssetTypeId').value = assetId;
        document.getElementById('deleteAssetTypeName').textContent = assetName;
    });
});

document.querySelectorAll('.editGenAssetBtn').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('editGenAssetId').value = this.dataset.id;
        document.getElementById('editGenName').value = this.dataset.name;
        document.getElementById('editQuantity').value = this.dataset.quantity;
        document.getElementById('editLocation').value = this.dataset.location;
        document.getElementById('editGenAssetType').value = this.dataset.type;
        document.getElementById('editGenDescription').value = this.dataset.description;
        document.getElementById('editPrice').value = this.dataset.price;
        document.getElementById('currentImage').src = this.dataset.image || '';


        modal.querySelector('#editGenAssetType').value = this.dataset.type;
    });
});

document.querySelectorAll('.deleteGenAssetBtn').forEach(button => {
    button.addEventListener('click', function () {
        const assetId = this.getAttribute('data-id');
        const assetName = this.getAttribute('data-name');
        document.getElementById('deleteGenAssetId').value = assetId;
        document.getElementById('deleteGenAssetName').textContent = assetName;
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const editButtons = document.querySelectorAll(".btn-edit-user");
    editButtons.forEach(button => {
        button.addEventListener("click", function () {
            document.getElementById("edit-user-id").value = this.dataset.id;
            document.getElementById("edit-username").value = this.dataset.username;
            document.getElementById("edit-name").value = this.dataset.name;
            document.getElementById("edit-role").value = this.dataset.role;
            document.getElementById("edit-status").value = this.dataset.status;
            document.getElementById("edit_employee_id").value = this.dataset.emp_id;
        });
    });
});

function showApproveModal(assetId) {
    // Set the hidden input value
    document.getElementById('approveAssetId').value = assetId;

    // Show the Bootstrap modal
    var approveModal = new bootstrap.Modal(document.getElementById('approveAssetModal'));
    approveModal.show();
}

function showDisapproveModal(assetId) {
    // Set the hidden input value
    document.getElementById('disapproveAssetId').value = assetId;

    // Show the Bootstrap modal
    var approveModal = new bootstrap.Modal(document.getElementById('disapproveAssetModal'));
    approveModal.show();
}

function showReturnModal(assetId) {
    document.getElementById('returnAssetId').value = assetId;
    var returnModal = new bootstrap.Modal(document.getElementById('returnModal'));
    returnModal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    const historyModal = document.getElementById('historyAssetModal');

    historyModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const assetId = button.getAttribute('data-id');

        // Show loading message
        document.getElementById('historyContent').innerHTML = '<p class="text-muted">Loading history...</p>';

        // Fetch data
        fetch('tools/load-assetHistory.php?id=' + assetId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('historyContent').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('historyContent').innerHTML = '<p class="text-danger">Error loading history.</p>';
                console.error(error);
            });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const repairedButtons = document.querySelectorAll('.btn-repaired-asset');
    const repairedInput = document.getElementById('repairedAssetId');
    const repairedTagText = document.getElementById('repairedAssetTagText');

    repairedButtons.forEach(button => {
        button.addEventListener('click', () => {
            const assetId = button.getAttribute('data-id');
            const assetTag = button.getAttribute('data-assettag');

            repairedInput.value = assetId;
            repairedTagText.textContent = assetTag;
        });
    });
});

document.getElementById('unassignReasonSelect').addEventListener('change', function () {
    const otherGroup = document.getElementById('otherReasonGroup');
    if (this.value === 'Other') {
        otherGroup.style.display = 'block';
        document.getElementById('OtherReason').required = true;
    } else {
        otherGroup.style.display = 'none';
        document.getElementById('OtherReason').required = false;
        document.getElementById('OtherReason').value = ''; // Clear input
    }
});

document.querySelectorAll('.btn-pending-status-unassign').forEach(btn => {
    btn.addEventListener('click', async function () {
        const assetId = this.getAttribute('data-id');

        const res = await fetch(`tools/load-approvalDetails.php?id=${assetId}`);
        const asset = await res.json();

        document.getElementById('employeeNameText').textContent = asset.EmployeeName || '--';
        document.getElementById('assetNameText').textContent = asset.AssetTagNumber || '--';
        document.getElementById('reasonText').textContent = asset.Reason || '--';
        document.getElementById('submittedOnText').textContent = asset.CreatedOn || '--';

        if (asset.Reason === 'Other' && asset.OtherReason) {
            document.getElementById('otherReasonLabel').classList.remove('d-none');
            document.getElementById('otherReasonText').classList.remove('d-none');
            document.getElementById('otherReasonText').textContent = asset.OtherReason;
        } else {
            document.getElementById('otherReasonLabel').classList.add('d-none');
            document.getElementById('otherReasonText').classList.add('d-none');
        }
    });
});

document.querySelectorAll('.btn-pending-status-assign').forEach(btn => {
    btn.addEventListener('click', async function () {
        const assetId = this.getAttribute('data-id');

        const res = await fetch(`tools/load-approvalDetails.php?id=${assetId}`);
        const asset = await res.json();

        document.getElementById('employeeNameText2').textContent = asset.EmployeeName || '--';
        document.getElementById('assetNameText2').textContent = asset.AssetTagNumber || '--';
        document.getElementById('reasonText2').textContent = asset.Reason || '--';
        document.getElementById('submittedOnText2').textContent = asset.CreatedOn || '--';

        if (asset.Reason === 'Other' && asset.OtherReason) {
            document.getElementById('otherReasonLabel2').classList.remove('d-none');
            document.getElementById('otherReasonText2').classList.remove('d-none');
            document.getElementById('otherReasonText2').textContent = asset.OtherReason;
        } else {
            document.getElementById('otherReasonLabel2').classList.add('d-none');
            document.getElementById('otherReasonText2').classList.add('d-none');
        }
    });
});

function loadApprovalDetails(assetId, invID) {
    fetch('tools/load-forApprovalDetails.php?id=' + assetId + '&invId=' + invID)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            document.getElementById('viewAssignedTo').value = data.AssignedToName || '--';
            document.getElementById('viewLocation').value = data.Location || '--';
            document.getElementById('viewDateAcquired').value = data.DateAcquired || '';
            document.getElementById('viewConditions').value = data.Conditions || '--';
            document.getElementById('viewRemarks').value = data.Remarks || '--';

            const img = document.getElementById('viewAssetImage');
            if (data.Image) {
                img.src = `image/assignimages/${data.Image}`;
                img.style.display = 'block';
            } else {
                img.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching details:', error);
        });
}

document.querySelectorAll('.btn-view-approval').forEach(button => {
    button.addEventListener('click', function () {
        const assetId = this.getAttribute('data-id');
        const invID = this.getAttribute('data-invID');
        loadApprovalDetails(assetId, invID);
    });
});

document.querySelectorAll('.open-confirm-modal').forEach(button => {
    button.addEventListener('click', function () {
        const approvalId = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const assetid = this.getAttribute('data-assetid');

        document.getElementById('asset_id').value = assetid;

        document.getElementById('modalApprovalId').value = approvalId;
        document.getElementById('modalAssetName').textContent = name;

        const modal = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
        modal.show();
    });
});

document.querySelectorAll('.open-reject-modal').forEach(button => {
    button.addEventListener('click', function () {
        const approvalId = this.getAttribute('data-id');
        const assetName = this.getAttribute('data-name');
        const assetId = this.getAttribute('data-assetid');

        document.getElementById('rejectApprovalId').value = approvalId;
        document.getElementById('rejectAssetId').value = assetId;
        document.getElementById('rejectAssetName').textContent = assetName;

        const modal = new bootstrap.Modal(document.getElementById('confirmRejectModal'));
        modal.show();
    });
});

//For Loading Purposes
(function ($) {
    'use strict';

    var $window = $(window);
    var zero = 0;

    // :: 1.0 PRELOADER ACTIVE CODE
    $(window).on("load", function () {
        $("#digimax-preloader").addClass("loaded");

        if ($("#digimax-preloader").hasClass("loaded")) {
            $("#preloader").delay(900).queue(function () {
                $(this).remove();
            });
        }
    });

}(jQuery));