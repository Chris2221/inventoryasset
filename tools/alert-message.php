<script>
    const url = new URL(window.location.href);
    const urlParams = new URLSearchParams(url.search);

    const status = urlParams.get('status');

    const statusMessages = {
        added: ['Asset has been successfully added.', 'success'],
        updated: ['Asset has been successfully updated.', 'success'],
        assigned: ['Asset has been successfully assigned. Please wait for Approval.', 'success'],
        unassigned: ['Asset has been successfully unassigned. Please wait for Approval.', 'success'],
        deleted: ['Asset has been successfully moved to Archived.', 'error'],
        useradded: ['User has been successfully added.', 'success'],
        userupdated: ['User has been successfully edited.', 'success'],
        userdeleted: ['User has been successfully deleted.', 'error'],
        employeeupdated: ['Employee has been successfully updated.', 'success'],
        employeeadded: ['Employee has been successfully added.', 'success'],
        categoryupdated: ['Category has been successfully updated.', 'success'],
        categoryadded: ['Category has been successfully added.', 'success'],
        categorydeleted: ['Category has been successfully deleted.', 'error'],
        generalassetadded: ['Asset has been successfully added.', 'success'],
        generalassetrestored: ['Asset has been successfully restored.', 'success'],
        generalassetupdated: ['Asset has been successfully updated.', 'success'],
        requestadded: ['Request has been successfully added.', 'success'],
        requestupdated: ['Request has been successfully updated.', 'success'],
        generalassetarchived: ['Asset has been successfully archived.', 'error'],
        outboundapproved: ['Outbound request has been successfully approved.', 'success'],
        outboundrejected: ['Outbound request has been successfully rejected.', 'success'],
        returned: ['Outbound request has been successfully returned.', 'success'],
        restored: ['Asset has been restored', 'success'],
        assetreceived: ['Asset has been received', 'success'],
        approvedDecommissioned: ['Asset has been decommissioned', 'error'],
        repaired: ['Repair transaction saved', 'success'],
        unauthorized: ['You are not the approver', 'error'],
        already_approved: ['You already approved this request', 'error']
        
    };

    if (status && statusMessages[status]) {
        const [message, type] = statusMessages[status];
        toastr[type](message);

        // Remove only the 'status' parameter
        urlParams.delete('status');

        // Reconstruct the query string
        const newQuery = urlParams.toString();
        const newUrl = `${url.pathname}${newQuery ? '?' + newQuery : ''}`;

        // Update the browser URL without reloading
        window.history.replaceState({}, '', newUrl);
    }
</script>
