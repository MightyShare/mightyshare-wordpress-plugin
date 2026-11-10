jQuery(function($) {
	/* Image picker */
	$('body').on('click', '.mightyshare-upload-img-button', function(e) {
		e.preventDefault();

		var button = $(this),
			value = button.next().val(),
			customUploader = wp.media({
				title: mightyshareObject.insertImage,
				library: { type: 'image' },
				button: { text: mightyshareObject.useThisImage },
				multiple: false
			}).on('select', function() {
				var attachment = customUploader.state().get('selection').first().toJSON();
				$(button).removeClass('button').html('<img class="simple-pre-img" src="' + attachment.url + '" style="max-width:300px;display:block;" />').next().next().val(attachment.id).next().show();
			}).on('close', function() {
				//alert('closed');
			});

		customUploader.on('open', function() {
			if (value) {
				var selection = customUploader.state().get('selection'),
					attachmentId = value;
				attachment = wp.media.attachment(attachmentId);
				attachment.fetch();
				selection.add(attachment ? [attachment] : []);
			}
		});
		customUploader.open();
	});

	$('body').on('click', '.mightyshare-remove-img-button', function() {
		$(this).hide().prev().val('').prev().prev().addClass('button').html(mightyshareObject.uploadImage);
		return false;
	});

	/* Share image preview / regenerate */
	$('body').on('click', '.mightyshare-preview-load, .mightyshare-preview-regenerate', function() {
		var wrap = $(this).closest('.mightyshare-preview'),
			regenerate = $(this).hasClass('mightyshare-preview-regenerate'),
			buttons = wrap.find('button'),
			status = wrap.find('.mightyshare-preview-status'),
			image = wrap.find('.mightyshare-preview-image');

		buttons.prop('disabled', true);
		status.text(regenerate ? mightyshareObject.rendering : mightyshareObject.loading);

		$.post(ajaxurl, {
			action: 'mightyshare_preview',
			_ajax_nonce: mightyshareObject.previewNonce,
			post_id: wrap.data('post-id'),
			regenerate: regenerate ? 1 : 0
		}).done(function(response) {
			if (!response || !response.success) {
				status.text(response && response.data && response.data.message ? response.data.message : mightyshareObject.failed);
				return;
			}
			var img = $('<img alt="">').attr('src', response.data.url);
			img.on('load', function() { status.text(''); });
			img.on('error', function() { img.remove(); status.text(mightyshareObject.failed); });
			image.empty().append(img);
		}).fail(function() {
			status.text(mightyshareObject.failed);
		}).always(function() {
			buttons.prop('disabled', false);
		});
	});
});

function toggleApiKeyFieldMask(field) {
	const selectedField = document.querySelector(field);
	selectedField.type = this.event.target.checked ? "text" : "password";
}

function getMightyShareTemplateValue(select) {
	// An empty value means "Default": the select carries the template it resolves to.
	return select.value || select.dataset.defaultTemplate || '';
}

function renderMightyShareTemplatePreview(select){
	const result = select.closest("td").querySelector(".mightyshare-image-preview");
	const templateSelected = getMightyShareTemplateValue(select);
	result.replaceChildren();
	if( templateSelected && templateSelected !== "screenshot-self" ){
		const img = document.createElement("img");
		img.alt = "";
		img.src = "https://api.mightyshare.io/template/preview/" + encodeURIComponent(templateSelected) + ".jpeg";
		result.appendChild(img);
	}
}

document.querySelectorAll(".mightyshare_template_field").forEach(item => {
  item.addEventListener('change', event => {
		renderMightyShareTemplatePreview(event.target);
	})
});

document.addEventListener("DOMContentLoaded", event => {
	document.querySelectorAll(".mightyshare_template_field").forEach(item => {
		renderMightyShareTemplatePreview(item);
	})
});

jQuery(document).ready(function(){
	jQuery(function() {
		jQuery('.mightyshare_color_field').wpColorPicker();
	});

	jQuery(".mightyshare-template-picker-button").on("click", function(e) {
		let templateSelected = getMightyShareTemplateValue(document.querySelector("#"+jQuery(e.currentTarget).attr("data-pickerfor")));
		jQuery("#mightyshare-template-picker-modal").attr("data-pickerfor", jQuery(e.currentTarget).attr("data-pickerfor"));
		jQuery("#mightyshare-template-picker-modal .template-block").removeClass('active').attr('aria-pressed', 'false').filter(function() {
			return jQuery(this).attr("data-mightysharetemplate") === templateSelected;
		}).addClass('active').attr('aria-pressed', 'true');
	});

	jQuery("#mightyshare-template-picker-modal .template-block").on("keydown", function(e) {
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault();
			jQuery(this).trigger("click");
		}
	});

	jQuery("#mightyshare-template-picker-modal .template-block").on("click", function(e) {
		var selectedTemplateField = jQuery(e.currentTarget).parent("#mightyshare-template-picker-modal").attr('data-pickerfor');
		var selectedTemplate = jQuery(e.currentTarget).attr("data-mightysharetemplate");
		jQuery("#"+selectedTemplateField).val(selectedTemplate);
		document.querySelector("#"+selectedTemplateField).dispatchEvent(new Event("change"));
		tb_remove();
	});
});
