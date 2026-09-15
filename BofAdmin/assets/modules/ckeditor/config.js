/**
 * @license Copyright (c) 2003-2019, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

 CKEDITOR.plugins.addExternal('youtube', 'plugins/youtube/');

 CKEDITOR.editorConfig = function( config ) {
	 // Define changes to default configuration here. For example:
	 // config.language = 'fr';
	 // config.uiColor = '#AADC6E';
	 config.enterMode = CKEDITOR.ENTER_BR;
	 config.shiftEnterMode = CKEDITOR.ENTER_P; //pressing the SHIFT + ENTER KEYS input <p>
	 config.autoParagraph = false; // stops automatic insertion of <p> on focus
	 config.language = 'en';
	 config.allowedContent = true;
	 config.removeFormatAttributes = '';
	 config.removePlugins = 'elementspath';
	 //config.fillEmptyBlocks = false;
	 config.basicEntities = false;
	 config.entities = false;
	 config.tabSpaces = 0;
	 config.forcePasteAsPlainText = false;

	 config.extraAllowedContent = 'span(*)';
	 config.entities_latin = false;
	 config.entities_greek = false;
	 config.entities_processNumerical = false;
	 config.fillEmptyBlocks = function (element) {
		 return true; // DON'T DO ANYTHING!!!!!
	 };
	 config.extraPlugins = 'templates,youtube';
	 config.youtube_responsive = true;
	 config.youtube_related = false;
 };


 // CKEDITOR.dtd.$removeEmpty['span'] = false;
 // CKEDITOR.dtd.$removeEmpty['i'] = false;
 // CKEDITOR.dtd.$removeEmpty['iframe'] = false;