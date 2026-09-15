<script src="<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckeditor/ckeditor.js"></script>
<!-- <script src="//cdn.ckeditor.com/4.13.1/full/ckeditor.js"></script> -->
<!-- <script src="<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckeditor/adapters/jquery.js"></script> -->
<script type="text/javascript">

  document.addEventListener('DOMContentLoaded', function() {
    // Function to initialize CKEditor
    function initCKEditor(selector, config) {
        config.licenseKey = '<?php echo $CK_licenseKey; ?>';
        document.querySelectorAll(selector).forEach(function(textarea) {
            CKEDITOR.replace(textarea, config);
        });
    }

    // CKEditor Full Configuration
    initCKEditor('textarea.ckeditor_full', {
        language: 'en',
        toolbar: [
            { name: 'document', items: [ 'Source'] },
            { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },
            { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
            { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
            { name: 'insert', items: [ 'Image', 'Table', 'Youtube'] },
            '/',
            { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
            { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
            { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
        ],
        filebrowserBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html',
        filebrowserImageBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html?type=Images',
        filebrowserUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
        filebrowserImageUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Images',
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P
    });

    // CKEditor Mini Configuration
    initCKEditor('textarea.ckeditor_mini', {
        language: 'en',
        toolbar: [
            { name: 'document', items: [ 'Source'] },
            { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
            { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
            { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
            { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
        ],
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P
    });

    // CKEditor BR Configuration
    initCKEditor('textarea.ckeditor_br', {
        language: 'en',
        toolbar: [
            { name: 'document', items: ['Source'] }
        ],
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P
    });

    // CKEditor No Source Configuration
    initCKEditor('textarea.ckeditor_no_source', {
        language: 'en',
        toolbar: [],
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P
    });

    // CKEditor Shop Configuration
    initCKEditor('textarea.ckeditor_shop', {
        language: 'en',

        toolbar: [
            { name: 'document', items: [ 'Source'] },
            { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },
            { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
            { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
            { name: 'insert', items: [ 'Image', 'Table', 'Youtube'] },
            '/',
            { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
            { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
            { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
        ],
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P
    });
  });

	// jQuery(document).ready(function() {

  //   jQuery( 'textarea.ckeditor_full' ).ckeditor({
  //     language: 'en',
  //     toolbar: [
  //       { name: 'document', items: [ 'Source'] },
  //       { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },

  //       { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
  //       { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
  //       { name: 'insert', items: [ 'Image', 'Table', 'Youtube'] },
  //               '/',
  //       { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
  //       { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
  //       { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
  //     ],
  //     filebrowserBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html',
  //     filebrowserImageBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html?type=Images',
  //     filebrowserUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
  //     filebrowserImageUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Images',
  //     enterMode : CKEDITOR.ENTER_BR,
  //     shiftEnterMode: CKEDITOR.ENTER_P
	// 	});

	// 	jQuery( 'textarea.ckeditor_mini' ).ckeditor({
  //     language: 'en',
	// 		toolbar: [
	// 			{ name: 'document', items: [ 'Source'] },
	// 			{ name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
	// 			{ name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
	// 			//{ name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
	// 			//{ name: 'insert', items: [ 'Image', 'Table'] },
	// 			{ name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
	// 			{ name: 'colors', items: [ 'TextColor', 'BGColor' ] }
	// 		],
  //     enterMode : CKEDITOR.ENTER_BR,
  //     shiftEnterMode: CKEDITOR.ENTER_P
	// 	});

	// 	jQuery( 'textarea.ckeditor_br' ).ckeditor({
  //     language: 'en',
	// 		toolbar: [
	// 			{ name: 'document', items: ['Source'] }
	// 		],
  //     enterMode : CKEDITOR.ENTER_BR,
  //     shiftEnterMode: CKEDITOR.ENTER_P
	// 	});

  //   jQuery( 'textarea.ckeditor_no_source' ).ckeditor({
  //     language: 'en',
	// 		// toolbar: [
	// 		// 	{ name: 'document', items: [] }
  //     // ],
  //     toolbar: [],
  //     enterMode : CKEDITOR.ENTER_BR,
  //     shiftEnterMode: CKEDITOR.ENTER_P
	// 	});

  //   jQuery( 'textarea.ckeditor_shop' ).ckeditor({
  //     language: 'en',
  //     toolbar: [
  //       { name: 'document', items: [ 'Source'] },
  //       { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },

  //       { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl' ] },
  //       { name: 'links', items: [ 'Link', 'Unlink', 'Anchor' ] },
  //       { name: 'insert', items: [ 'Image', 'Table', 'Youtube'] },
  //               '/',
  //       { name: 'styles', items: [ 'Styles', 'Format', 'Font', 'FontSize' ] },
  //       { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat' ] },
  //       { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
  //     ],
  //     // filebrowserBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html',
  //     // filebrowserImageBrowseUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/ckfinder.html?type=Images',
  //     // filebrowserUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
  //     // filebrowserImageUploadUrl: '<?php echo $_Config_live_site;?>/BofAdmin/assets/modules/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Images',
  //     enterMode : CKEDITOR.ENTER_BR,
  //     shiftEnterMode: CKEDITOR.ENTER_P
	// 	});
  // });

</script>