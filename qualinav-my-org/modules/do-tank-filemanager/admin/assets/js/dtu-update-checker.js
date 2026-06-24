(function ($) {
    if (typeof dtuUpdater === 'undefined') return;

    const container = $('.dtu-update-panel');
    if (!container.length) return;

    const statusBox     = $('#dtu-update-status');
    const spinner       = $('#dtu-update-spinner');
    const checkBtn      = $('#dtu-check-update');
    const updateBtn     = $('#dtu-update-now');
    const currentSpan   = $('#dtu-current-version');
    const changelogBox  = $('#dtu-update-changelog');
    const changelogBody = changelogBox.find('.dtu-changelog-content');

    const setStatus = (type, message) => {
        statusBox.removeClass('success error');
        if (type) statusBox.addClass(type);
        statusBox.text(message);
    };

    const toggleSpinner = (show) => {
        spinner.css('visibility', show ? 'visible' : 'hidden')
               .toggleClass('is-active', show);
    };

    const disableButtons = (disabled) => {
        checkBtn.prop('disabled', disabled);
        updateBtn.prop('disabled', disabled);
    };

    checkBtn.on('click', () => {
        setStatus('', dtuUpdater.strings.checking);
        toggleSpinner(true);
        disableButtons(true);
        changelogBox.hide();

        $.post(dtuUpdater.ajaxUrl, {
            action: 'dtu_check_update',
            nonce: dtuUpdater.nonce
        })
        .done((res) => {
            if (!res || !res.success) {
                throw new Error(res?.data?.message || dtuUpdater.strings.error);
            }

            const data = res.data;

            if (data.status === 'update_available') {
                setStatus('success', data.message);
                updateBtn
                    .show()
                    .data('download', data.download_url);

                if (data.changelog && data.changelog.trim() !== '') {
                    changelogBody.html(data.changelog);
                    changelogBox.show();
                }
            } else {
                setStatus('success', data.message || dtuUpdater.strings.upToDate);
                updateBtn.hide().removeData('download');
                changelogBox.hide();
            }
        })
        .fail((xhr) => {
            const message = xhr?.responseJSON?.data?.message || dtuUpdater.strings.error;
            setStatus('error', message);
            updateBtn.hide().removeData('download');
            changelogBox.hide();
        })
        .always(() => {
            toggleSpinner(false);
            disableButtons(false);
        });
    });

    updateBtn.on('click', () => {
        const downloadUrl = updateBtn.data('download');

        if (!downloadUrl) {
            setStatus('error', dtuUpdater.strings.error);
            return;
        }

        setStatus('', dtuUpdater.strings.installing);
        toggleSpinner(true);
        disableButtons(true);

        $.post(dtuUpdater.ajaxUrl, {
            action: 'dtu_do_update',
            nonce: dtuUpdater.nonce,
            download_url: downloadUrl
        })
        .done((res) => {
            if (!res || !res.success) {
                throw new Error(res?.data?.message || dtuUpdater.strings.error);
            }

            const data = res.data;

            setStatus('success', data.message);
            updateBtn.hide().removeData('download');
            changelogBox.hide();

            if (data.latest_version) {
                currentSpan.text(data.latest_version);
            }

            if (data.reload) {
                setStatus('success', 'Update successful. Reloading…');
                setTimeout(() => location.reload(), 1500);
            }
        })
        .fail((xhr) => {
            const message = xhr?.responseJSON?.data?.message || dtuUpdater.strings.error;
            setStatus('error', message);
        })
        .always(() => {
            toggleSpinner(false);
            disableButtons(false);
        });
    });

})(jQuery);
