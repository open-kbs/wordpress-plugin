(function(wp) {
    const { subscribe, select } = wp.data;
    let wasSaving = false;

    // Simple implementation 
    subscribe(() => {
        const editor = select('core/editor');
        const currentIsSaving = editor.isSavingPost();
        if (wasSaving && !currentIsSaving) {
            const isAutosaving = editor.isAutosavingPost();
            const isDirty = editor.isEditedPostDirty();
            if (!isAutosaving && !isDirty) {
                if (typeof jQuery !== 'undefined') {
                    jQuery(document).trigger('startOpenKBSPolling');
                }
            }
        }

        wasSaving = currentIsSaving;
    });
})(window.wp);