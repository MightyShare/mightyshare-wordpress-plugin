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
function renderMightyShareTemplatePreview(){
	const result = document.querySelector(".mightyshare-image-preview");
	const templateSelected = document.querySelector(".default_template_field").value;
	if( templateSelected != "screenshot-self" ){
		result.innerHTML = `<img src="https://api.mightyshare.io/template/preview/${templateSelected}.png">`;
	}else{
		result.innerHTML = ``;
	}
}

const selectElement = document.querySelector(".default_template_field");
selectElement.addEventListener("change", (event) => {
	renderMightyShareTemplatePreview();
}); document.addEventListener("DOMContentLoaded",renderMightyShareTemplatePreview);

jQuery(document).ready(function(){
	jQuery(function() {
		jQuery('.mightyshare_color_field').wpColorPicker();
	});
});
