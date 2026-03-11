jQuery(document).ready(($) => {
    $(document).on('click', '.step-upload-image', function (e) {
        e.preventDefault();

        const button = $(this);
        const field = button.closest('.step-image-field');
        const idInput = field.find('.step-image-id');
        const preview = field.find('.step-image-preview');
        const removeBtn = field.find('.step-remove-image');

        const frame = wp.media({
            title: stepOptionsL10n.title || 'Select an image',
            button: { text: stepOptionsL10n.button || 'Use the image' },
            multiple: false,
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            idInput.val(attachment.id);
            preview.html(
                '<img src="' +
                    (attachment.sizes.thumbnail
                        ? attachment.sizes.thumbnail.url
                        : attachment.url) +
                    '" alt="" style="max-width:200px; height:auto; display:block; margin:10px 0;">'
            );
            button.text(stepOptionsL10n.replace || 'Replace image');
            removeBtn.show();
        });

        frame.open();
    });

    $(document).on('click', '.step-remove-image', function (e) {
        e.preventDefault();
        const field = $(this).closest('.step-image-field');
        field.find('.step-image-id').val('');
        field.find('.step-image-preview').html('');
        field
            .find('.step-upload-image')
            .text(stepOptionsL10n.select || 'Select an image');
        $(this).hide();
    });
});
