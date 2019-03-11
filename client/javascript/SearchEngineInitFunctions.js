/**
 * You can set the following settings (main ones!):
 *  * SearchEngineInitFunctions.delay
 *  * SearchEngineInitFunctions.minimumNumberOfCharacters
 *  * SearchEngineInitFunctions.useInfiniteScroll
 *  * SearchEngineInitFunctions.useAutoComplete
 *  * SearchEngineInitFunctions.updateBrowserHistory
 *
 * The variables are explained below.
 *
 * Autocomplete uses the awesomeplete library
 * The form is submitted using jQuery.form: http://malsup.com/jquery/form/
 *
 */


;(function($) {
	jQuery(document).ready(function() {
		SearchEngineInitFunctions.init();
	});
})(jQuery);

var SearchEngineInitFunctions = {

	/**
	 * main settings
	 * that are often changed...
	 */

	/**
	 * number of milliseconds before the form self-submits
	 * @var int
	 */
	delay: 200,

	/**
	 * number of characters entered before the form may self-submit
	 * @var int
	 */
	minimumNumberOfCharacters: 2,

	/**
	 * should inifinite scroll be used (more results show up as you scroll down)
	 *
	 * @var boolean
	 */
	useInfiniteScroll: false,

	/**
	 * should auto complete be used (auto-completing words entered in the keyword box)
	 *
	 * @var boolean
	 */
	useAutoComplete: false,

	/**
	 * should the URL of the page change automatically as the search is carried out
	 * using ajax...
	 *
	 * @var boolean
	 */
	updateBrowserHistory: false,

	/**
	 * selectors and classes used
	 * do not change unless you have to...
	 */

	/**
	 *
	 * @var string
	 */
	loadingClass: "loading",

	/**
	 * class used to show that the form is not active
	 * because no keywords have been entered
	 * @var string
	 */
	formWithoutSearchClass: 'searchEngineDormant',

	/**
	 * class used to show that keywords have been active
	 * @var string
	 */
	formWithSearchClass: 'searchEngineAwake',

	/**
	 * keyword box selector
	 * @var string
	 */
	formInputSelector: '#SearchEngineKeywords input',

	/**
	 * displayed input class
	 * when set this hides the default keyword field so
	 * that you can add one somewhere else on your page.
	 *
	 * e.g. input.changeMeToChangeTheKeywordBox
	 * @var string
	 */
	displayedFormInputSelector: "",

	/**
	 * sort and filter by selector.
	 * @var string
	 */
	formFilterSortSelector: 'div#SortBy.field input, div#FilterFor.field input',

	/**
	 * the literal field in which the results are shown
	 * @var string
	 */
	targetSelector: "#SearchEngineResultsHolderOuter",


	/**
	 * the selector for the UL that holds the list of results...
	 * @var string
	 */
	resultsList: "ul.searchEngineResultsList",

	/**
	 * the selector for each individual result item
	 * @var string
	 */
	resultItemSelector: "li.searchEngineResultItem",

	/**
	 * the pagination section selector
	 * @var string
	 */
	paginationClass: "p.searchEnginePageInfo",

	/**
	 * the selector for the div that shows while the results are being
	 * retrieved...
	 * @var string
	 */
	retrievalMesage: "#SearchEngineRetrievalMessage",

	/**
	 * the selector for the a tag linking to the search results...
	 * retrieved...
	 * @var string
	 */
	resultsLink: "#SearchEngineResultsLink",

	/**
	 * name of the attribute that
	 * tells us the ID of the SearchEngineDataObject
	 * @var string
	 */
	dataObjectAttributeIdentifier: "data-searchenginedataobjectid",

	/**
	 * other settings
	 */

	/**
	 * infinite scroll settings
	 * @object
	 */
	infiniteScrollLoadingSettings: {
		finishedMsg: "<em class='searchEngineEndNote'>End of results.</em>",
		img: "/searchengine/images/loading.gif",
		msgText: "<em class='searchEngineLoadingMoreResults'>Loading ...</em>",
	},

	/**
	 * set by PHP - do not change
	 * @var string
	 */
	formSelector: '#SearchEngineBasicForm_SearchEngineBasicForm',

	/**
	 * internal variables
	 * set by the JS itself...
	 */

	/**
	 * do not change...
	 * @var string
	 */
	lastEntry: "",

	/**
	 * do not change...
	 * @var string
	 */
	timeOutVariable: null,

	/**
	 * do not change...
	 * @var string
	 */
	keywordList: {},

	/**
	 * we look in the URL for this get variable as foo in the following url
	 * www.mysite.co.nz?foo=bar
	 * @var string
	 */
	getVariableToRecogniseSearch: "SearchEngineKeywords",

	/**
	 * kicks everything into action
	 * 	
	 */ 	
	init: function(){
		SearchEngineInitFunctions.ajaxifyForm();
		SearchEngineInitFunctions.searchFormInputListener();
		SearchEngineInitFunctions.searchFormFilterSortListener();
		SearchEngineInitFunctions.enableAutocomplete();
		SearchEngineInitFunctions.resultClickListener();
		SearchEngineInitFunctions.infiniteScrollBinder();
	},

	/**
	 * makes the form into an ajax form
	 * 	
	 */ 	
	ajaxifyForm: function(){
		var options = {
			target:        SearchEngineInitFunctions.targetSelector,   // target element(s) to be updated with server response
			beforeSubmit:  SearchEngineInitFunctions.showRequest,  // pre-submit callback
			success:       SearchEngineInitFunctions.showResponse,
			cache: false
		};
		if(typeof this.getUrlVars()[this.getVariableToRecogniseSearch] != 'undefined') {
			var formClass = SearchEngineInitFunctions.formWithSearchClass;
		}
		else {
			var formClass = SearchEngineInitFunctions.formWithoutSearchClass;
		}
		jQuery(SearchEngineInitFunctions.formSelector)
			.ajaxForm(options)
			.addClass(formClass);
	},

	/**
	 * This function listens for input in the search form and submits the from once the input field contains
	 * the minimum number of characters required.
	 * It continues to submit the form after each additional character.
	 */
	searchFormInputListener: function() {
		var inputFieldSelector = SearchEngineInitFunctions.formInputSelector;
		if(SearchEngineInitFunctions.displayedFormInputSelector) {
			jQuery(SearchEngineInitFunctions.formInputSelector).closest('div.field').hide();
			inputFieldSelector += ", "+SearchEngineInitFunctions.displayedFormInputSelector;
		}
		jQuery(inputFieldSelector).on(
			'input',
			function(){
				window.clearTimeout(SearchEngineInitFunctions.timeOutVariable);
				var val = jQuery.trim(jQuery(this).val());
				if(val != SearchEngineInitFunctions.lastEntry) {
					SearchEngineInitFunctions.lastEntry = val;
					if(val.length < SearchEngineInitFunctions.minimumNumberOfCharacters) {
						SearchEngineInitFunctions.submitForm();
					}
					SearchEngineInitFunctions.timeOutVariable = window.setTimeout(
						SearchEngineInitFunctions.submitForm,
						SearchEngineInitFunctions.delay
					);
				}
			}
		);
		jQuery(inputFieldSelector).on(
			'keypress',
			function(event) {
				if(event.which == 13) {
					event.preventDefault();
					SearchEngineInitFunctions.submitForm();
				}
			}
		);
		if(SearchEngineInitFunctions.displayedFormInputSelector) {
			jQuery(inputFieldSelector).on(
				"keyup blur change",
				function() {
					var copyValue = jQuery(SearchEngineInitFunctions.displayedFormInputSelector).val();
					jQuery(SearchEngineInitFunctions.formInputSelector).val(copyValue);
				}
			);
		}
	},

	/* This function listens for input in the search form and submits the from once the input field contains
	 * the minimum number of characters required.
	 * It continues to submit the form after each additional character.
	 */
	searchFormFilterSortListener: function() {
		jQuery(SearchEngineInitFunctions.formFilterSortSelector).on(
			'change',
			function(){
				SearchEngineInitFunctions.submitForm();
			}
		);
	},

	submitForm: function() {
		window.clearTimeout(SearchEngineInitFunctions.timeOutVariable);
		var len = jQuery(SearchEngineInitFunctions.formInputSelector).val().length;
		if(len >= SearchEngineInitFunctions.minimumNumberOfCharacters){
			jQuery(SearchEngineInitFunctions.retrievalMesage).show();
			jQuery(SearchEngineInitFunctions.formSelector)
				.removeClass(SearchEngineInitFunctions.formWithoutSearchClass)
				.addClass(SearchEngineInitFunctions.formWithSearchClass)
				.submit();
		}
		else {
			if(!!this.useInfiniteScroll) {
				jQuery(SearchEngineInitFunctions.resultsList).infinitescroll('unbind');
			}
			jQuery(SearchEngineInitFunctions.targetSelector).empty();
			jQuery(SearchEngineInitFunctions.formSelector)
				.addClass(SearchEngineInitFunctions.formWithoutSearchClass)
				.removeClass(SearchEngineInitFunctions.formWithSearchClass);
		}
	},

	showRequest: function(formData, jqForm, options) {
		var queryString = jQuery.param(formData);
		jQuery(SearchEngineInitFunctions.targetSelector).addClass(SearchEngineInitFunctions.loadingClass);
		jQuery(SearchEngineInitFunctions.formSelector).addClass(SearchEngineInitFunctions.loadingClass);
		if(SearchEngineInitFunctions.displayedFormInputSelector) {
			jQuery(SearchEngineInitFunctions.displayedFormInputSelector).addClass(SearchEngineInitFunctions.loadingClass);
		}
		// jqForm is a jQuery object encapsulating the form element.  To access the
		// DOM element for the form do this:
		// var formElement = jqForm[0];
		if(SearchEngineInitFunctions.updateBrowserHistory) {
			var resultsLink = jqForm[0].action + "?" + queryString;
			history.pushState("", "", resultsLink);
		}
		return true;
	},

	showResponse: function(responseText, statusText, xhr, $form)  {
		jQuery(SearchEngineInitFunctions.formSelector).removeClass(SearchEngineInitFunctions.loadingClass);
		jQuery(SearchEngineInitFunctions.targetSelector).removeClass(SearchEngineInitFunctions.loadingClass);
		if(SearchEngineInitFunctions.displayedFormInputSelector) {
			jQuery(SearchEngineInitFunctions.displayedFormInputSelector).removeClass(SearchEngineInitFunctions.loadingClass);
		}
		jQuery(SearchEngineInitFunctions.retrievalMesage).hide();

		SearchEngineInitFunctions.infiniteScrollBinder();
	},

	resultClickListener: function(){
		jQuery(SearchEngineInitFunctions.formSelector).on(
			"click",
			SearchEngineInitFunctions.resultItemSelector+" a",
			function(event){
				event.preventDefault();
				var id = jQuery(this).parents(SearchEngineInitFunctions.resultItemSelector).attr(SearchEngineInitFunctions.dataObjectAttributeIdentifier);
				var link = jQuery(this).attr('href');
				var betterURL = jQuery("base").attr("href") + "searchenginerecordclick/add/" + id + "/?finaldestination="+encodeURIComponent(link);
				window.location.href = betterURL;
				return false;
			}
		);
	},

	infiniteScrollBinder: function() {
		if(!!this.useInfiniteScroll) {
			var getVars = this.getUrlVars();
			var startGetVar = getVars["start"];
			if(typeof  startGetVar != 'undefined' && startGetVar > 0) {
				//do not attach because you would get weird results...
			}
			else {
				jQuery(SearchEngineInitFunctions.resultsList).infinitescroll(
					{
						//debug  : true,
						navSelector  : SearchEngineInitFunctions.paginationClass, // selector for the paged navigation (it will be hidden)
						nextSelector : SearchEngineInitFunctions.paginationClass+" a.next", // selector for the NEXT link (to page 2)
						itemSelector : SearchEngineInitFunctions.resultsList+" "+SearchEngineInitFunctions.resultItemSelector, // selector for all items you'll retrieve
						loading      : SearchEngineInitFunctions.infiniteScrollLoadingSettings,
						path         : function(page) {
														 var resultsLink = jQuery(SearchEngineInitFunctions.resultsLink).attr("href");
														 var numberOfResultsPerPage = jQuery(SearchEngineInitFunctions.resultsLink).attr("data-items-per-page");
														 if (typeof numberOfResultsPerPage == 'undefined') {numberOfResultsPerPage = 10;}
														 return resultsLink + "&start="+(numberOfResultsPerPage * (page-1));
													 }
					}
				);
			}
		}
	},

	enableAutocomplete: function(){
		if(!!this.useAutoComplete) {
			var inputFieldSelector = SearchEngineInitFunctions.formInputSelector;
			if(SearchEngineInitFunctions.displayedFormInputSelector) {
				inputFieldSelector = SearchEngineInitFunctions.displayedFormInputSelector;
			}
			var domElement = jQuery(inputFieldSelector)[0];
			//set delay to 30 seconds, as we want people to use dropdown to select
			this.delay = 30000;
			new Awesomplete(
				domElement,
				{
					list: SearchEngineInitFunctions.keywordList,
					minChars: 3,
					maxItems: 7,
					autoFirst: false,
					filter: function(text, input) {
						//return Awesomplete.FILTER_CONTAINS(text, input.match(/[^\s]*$/)[0]);
						return Awesomplete.FILTER_STARTSWITH(text, input.match(/[^\s]*$/)[0]);
					},

					replace: function(text) {
						var val = this.input.value;
						var arr = val.split(/,?\s+/);
						arr.pop();
						var string = arr.join(" ");
						if(string.length) {
							string +=" ";
						}
						this.input.value = string + text + " ";
					}
				}
			);
			jQuery(inputFieldSelector).on(
				'awesomplete-selectcomplete',
				function(e){
					jQuery(inputFieldSelector).trigger("keyup");
					domElement.value = jQuery.trim(domElement.value);
					SearchEngineInitFunctions.submitForm();
				}
			);
		}
	},

	getUrlVars: function() {
		var vars = {};
		var parts = window.location.href.replace(
			/[?&]+([^=&]+)=([^&]*)/gi,
			function(m,key,value) {
				vars[key] = value;
			}
		);
		return vars;
	}

}

