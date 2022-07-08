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
				console.log(attachment);
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
});

function toggleApiKeyFieldMask(field) {
	const selectedField = document.querySelector(field);
	selectedField.type = this.event.target.checked ? "text" : "password";
}

function getMightyShareTemplateValue(event) {
	let templateSelected = event.value;
	if( ! templateSelected ){
		if( event.options[event.selectedIndex].text.match(/\((.*)\)/) ){
			templateSelected = event.options[event.selectedIndex].text.match(/\((.*)\)/).pop();
		}
	}
	return templateSelected;
}

function renderMightyShareTemplatePreview(event){
	const result = event.closest("td").querySelector(".mightyshare-image-preview");
	let templateSelected = getMightyShareTemplateValue(event);
	if( templateSelected && templateSelected != "screenshot-self" ){
		result.innerHTML = `<img src="https://api.mightyshare.io/template/preview/${templateSelected}.jpeg">`;
	}else{
		result.innerHTML = ``;
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
		jQuery("#mightyshare-template-picker-modal .template-block").removeClass('active');
		jQuery("#mightyshare-template-picker-modal .template-block[data-mightysharetemplate="+templateSelected+"]").addClass('active');
	});

	jQuery("#mightyshare-template-picker-modal .template-block").on("click", function(e) {
		var selectedTemplateField = jQuery(e.currentTarget).parent("#mightyshare-template-picker-modal").attr('data-pickerfor');
		var selectedTemplate = jQuery(e.currentTarget).data("mightysharetemplate");
		jQuery("#"+selectedTemplateField).val(selectedTemplate);
		document.querySelector("#"+selectedTemplateField).dispatchEvent(new Event("change"));
		tb_remove();
	});
});
