Search Engine
===================

This module is intended for medium-size websites where the SS built-in full-text search
engine is not good enough, but something like SOLR is overkill.

This module is intended to be fast, customisable, and reasonably scalable.
It also has a fully developed front-end implementation that looks great
almost straight out of the box.

The search engine module does the following:

BACK-END PREPARATION
--------------------
 - Identify what objects and what fields in those objects need to be indexed.
 - Prepare a keyword list and a full-content index, grouped into LEVEL 1 and LEVEL 2.
 - LEVEL 1 are important, salient fields, such as Title and LEVEL 2 is content from less important fields such as Content.
 - As part of an object, you can include has_one, has_many, and many_many relationship data (belongs_many_many coming soon).


KEYWORD MANIPULATION
--------------------
 - You can add words to be removed from search, such as stop words (e.g. and, the)
 - You can add words to be replaced with other ones (e.g. NZ with New Zealand)
 - You can remove punctuation, etc...

SMART SEARCH
------------
The search carried out by this module returns a list of Data Objects sorted
in a particular order.  The search uses some caching and other tricks to do this fast and meaningful.

FRONT-END
---------
 - You can add a search form to any page using the `$SearchEngineBasicForm` variable.
    Also available are: `$SearchEngineSuperBasicForm` and `$SearchEngineCustomForm`.
    Please have a look at: `/searchengine/code/extensions/ContentControllerExtension.php`
    to see how to use the `SearchEngineBasicForm`.

ANALYSIS
--------
 - The module provides basic analytics of searches carried out on the site.


DEPENDENCIES
------------
There are no additional requirements for the Silverstripe Search.
There are two JS libraries that are used (awesomplete and infinite scroll),
but you can make the module work without them.


CUSTOMISATION STRATEGY
----------------------
To customise this module, you can use many of the standard strategies.
Below is a list tricks (just some examples - not conclusive), from easy to difficult
  - theme CSS (copy CSS files from `/searchengine/css/` to `/themes/mytheme_searchengine/css/`)
  - theme the templates - similar to CSS (copy to theme and adjust), but you can also create new templates for displaying
     objects in the search results.
  - change the yml settings.  Please review /searchengine/\_config/searchengine.yml.example for examples.
  - extend `SearchEngineBasicForm` e.g. `MySearchEngineBasicForm extends SearchEngineBasicForm {...}`
  - add/change language definitions (see. `/searchengine/lang/`)
  - customise the indexing by adding a number of methods to indexed dataobjects (use the `SearchEngineMakeSearchable` interface).
  - add filters and sorters (see `/searchengine/code/filters` and `/searchengine/code/sorters` for examples).

CUSTOMISATION OF INDEXING
-------------------------
First of all you need to decide what DataObjects are going to be indexed.
You do this as follows:
    `yml

    File:
      extensions:
        - SearchEngineMakeSearchable
      search_engine_full_contents_fields_array:
        1:
          - Title
        2:
          - Content

    HomePage:
      search_engine_full_contents_fields_array:
        1:
          - Title
          - MenuTitle
          - HeaderCustomHeading
          - HeaderCustomSubHeading
        2:
          - Content
          - MyThirdDataObject.Title
    `


CUSTOMISATION OF SEARCHING
--------------------------
 - you can swap out the back-end search engine. So far a MYSQL FullText has been implemented.
    This can be done by setting another class in the configs.

CUSTOMISATION OF RESULTS DISPLAY
--------------------------------
For customisation of the
 - you can Requirements::block("searchengine/javascript/SearchEngineInitFunctions.js"); and add your own JS.
 - you can replace variables or methods in the JS `SearchEngineInitFunctions` object,
    by writing your own JS like so:
        SearchEngineInitFunctions.myVar = "foo"
        SearchEngineInitFunctions.myMethod = function(){...}
    this will replace methods and variables in SearchEngineInitFunctions


HOW TO INDEX AN OBJECT
----------------------


HOW TO ADD A SEARCH FORM
------------------------
