jQuery( function( $ ){

	// color picker
	// if( $.fn.iris ) {
	// 	$('.simple-color-field').iris( { });
	// }

	$("body").on('keydown keyup', '.with-simple-counter', function(){

		var l = $(this).val().length;
		$(this).parent().find('.simple-counter').children('span').text( l );
	});

	/* images */
	$('body').on('click', '.simple-upload-img-button', function(e){
		e.preventDefault();

  	var button = $(this),
				value = button.next().val(),
  		  customUploader = wp.media({
					title: simpleObject.insertImage,
					library : {type : 'image'},
					button: {text: simpleObject.useThisImage},
					multiple: false
				}).on('select', function() {
					var attachment = customUploader.state().get('selection').first().toJSON();
					console.log( attachment );
					$(button).removeClass('button').html('<img class="simple-pre-img" src="' + attachment.url + '" style="max-width:300px;display:block;" />').next().next().val(attachment.id).next().show();
				}).on('close',function(){
					//alert('closed');
				});

		customUploader.on('open',function() {

			if( value ) {

			  var selection = customUploader.state().get('selection'),
						attachmentId = value;

			  attachment = wp.media.attachment( attachmentId );
			  attachment.fetch();
			  selection.add( attachment ? [attachment] : [] );

			}

		});

		customUploader.open();

	});

	$('body').on('click', '.simple-remove-img-button', function(){

		$(this).hide().prev().val('').prev().prev().addClass('button').html( simpleObject.uploadImage );
		return false;

	});

	/* files */
	$('body').on('click', '.simple-upload-file-button', function(e){
		e.preventDefault();

  	var button = $(this),
				value = button.next().val(),
  		  customUploader = wp.media({
					title: simpleObject.insertImage,
					//library : {type : 'image'},
					button: {text: simpleObject.useThisImage},
					multiple: false
				}).on('select', function() {
					var attachment = customUploader.state().get('selection').first().toJSON();
					console.log( attachment );
					$(button).removeClass('button').html('<a class="simple-pre-file" href="' + attachment.url + '">your file</a>').next().next().val(attachment.id).next().show();
				}).on('close',function(){
					//alert('closed');
				});

		customUploader.on('open',function() {

			if( value ) {

			  var selection = customUploader.state().get('selection'),
						attachmentId = value;

			  attachment = wp.media.attachment( attachmentId );
			  attachment.fetch();
			  selection.add( attachment ? [attachment] : [] );

			}

		});

		customUploader.open();

	});

	/**
	 * Gallery
	 */

	$('.simple-gallery-field').sortable({ items:'li', cursor:'-webkit-grabbing', scrollSensitivity:40 });

	$('.simple-upload-images-button').click( function(e){
		e.preventDefault();

		var button = $(this),
				container = button.parent().find('.simple-gallery-field'),
				name = button.data('name'),
	    	custom_uploader = wp.media({
					title: simpleObject.insertImages,
					library : {type : 'image'},
					button: {text: simpleObject.useThisImages},
					multiple: true
				}).on('select', function() {

					var attachments = custom_uploader.state().get('selection').map(function( attachment ) {
						attachment.toJSON();
            return attachment;
					}),
					i;

          for (i = 0; i < attachments.length; ++i) {

						container.append('<li data-id="' + attachments[i].id + '"><span style="background-image:url(' + attachments[i].attributes.url + ')"></span><a href="#" class="simple-gallery-remove">&times;</a><input type="hidden" name="' + name + '[]" value="' + attachments[i].id + '" /></li>');

          }
					/* refresh sortable */
					$( ".simple-gallery-field" ).sortable( "refresh" );

				}).open();
		});

		/*
		 * Remove certain images
		 */
		$('body').on('click', '.simple-gallery-remove', function( event ){

			event.preventDefault();

			var id = $(this).parent().attr('data-id'),
					gallery = $(this).parent().parent();

			$(this).parent().remove();
			gallery.sortable( "refresh" );

		});

		/*
		 * Selected item
		 */
		$('body').on('mousedown', '.simple-gallery-field li', function(){
			var el = $(this);
			el.parent().find('li').removeClass('simple-gallery-active');
			el.addClass('simple-gallery-active');
		});

		/* Datepicker */
		$(".simple-datepicker").each(function(){
			var $this = $(this);
			$this.datepicker({
				showAnim: false,
				dateFormat: $this.data( 'dateformat' ),
				minDate : $this.data( 'mindate' ),
				maxDate : $this.data( 'maxdate' )
			});
		});

});
