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
function renderMightyShareTemplatePreview(event){
	const result = event.closest("td").querySelector(".mightyshare-image-preview");
	const templateSelected = event.value;
	if( templateSelected && templateSelected != "screenshot-self" ){
		result.innerHTML = `<img src="https://api.mightyshare.io/template/preview/${templateSelected}.png">`;
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
		jQuery("#mightyshare-template-picker-modal").attr("data-pickerfor", jQuery(e.currentTarget).attr("data-pickerfor"));
		jQuery("#mightyshare-template-picker-modal .template-block").removeClass('active');
		jQuery("#mightyshare-template-picker-modal .template-block[data-mightysharetemplate="+jQuery("#"+jQuery(e.currentTarget).attr("data-pickerfor")).val()+"]").addClass('active');
	});

	jQuery("#mightyshare-template-picker-modal .template-block").on("click", function(e) {
		var selectedTemplateField = jQuery(e.currentTarget).parent("#mightyshare-template-picker-modal").attr('data-pickerfor');
		var selectedTemplate = jQuery(e.currentTarget).data("mightysharetemplate");
		jQuery("#"+selectedTemplateField).val(selectedTemplate);
		document.querySelector("#"+selectedTemplateField).dispatchEvent(new Event("change"));
		tb_remove();
	});
});

function mightyshareApiKeyCheck(apikey) {
	if(document.querySelector('#mightyshare-api-key-status')){
		const data = { 'apikey': apikey };

		fetch('https://api.mightyshare.io/validate-key/', {
			method: 'POST',
			mode: 'cors',
			headers: {
			  'Content-Type': 'application/json'
			},
			body: JSON.stringify(data),
		})
		.then(response => response.json())
		.then(data => {
			document.querySelector('#mightyshare-api-key-status').innerHTML = data.message;
			document.querySelector('#mightyshare-api-key-status').classList.add("loaded");
			document.querySelector('#mightyshare-api-key-status').classList.add(data.type);
		})
		.catch((error) => {
		  console.error('Error:', error);
		});
	}
}
