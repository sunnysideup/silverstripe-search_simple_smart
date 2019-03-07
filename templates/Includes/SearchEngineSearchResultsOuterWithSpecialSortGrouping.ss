<div id="SearchEngineSearchResultsInner">
	<h2 class="result-page-hd">Search Results for <em>$SearchedFor</em></h2>
	<p id="SearchEngineRetrievalMessage" class="message good">Retrieving Search Results.</p>

<% if ResultsGrouped %>
	<% loop ResultsGrouped.GroupedBy(SpecialSortGroup).sort(SpecialSortGroup) %>
	<div class="search-result-group">
	<% if $SpecialSortGroup = SortGroup1 %>
	<h4 class="search-people-hd">PEOPLE</h4>
	<% else %>
	<h4 class="search-others-hd">OTHER RESULTS</h4>
	<% end_if %>
	<ul class="searchEngineResultsList $SpecialSortGroup">
		<% loop Children %>
		<li data-searchenginedataobjectid="$ID" class="searchEngineResultItem searchEngineItemFor$DataObjectClassName <% if DataObjectClassName != PersonPage %>search-item-general<% end_if %>">
			<% if $Up.IsMoreDetailsResult %>$HTMLOutputMoreDetails<% else %>$HTMLOutput<% end_if %></li>
		<% end_loop %>
	</ul>
	</div>
	<% end_loop %>

	<p id="SearchEngineResultsLinkHolder"><a id="SearchEngineResultsLink" href="$Link" data-items-per-page="$NumberOfItemsPerPage">$Count results</a></p>
	<p class="searchEnginePageInfo">
	<% if $Results.MoreThanOnePage %>
		<% if $Results.NotFirstPage %>
			<a class="prev" href="/$Results.PrevLink">Prev</a>
		<% end_if %>
		<% loop $Results.Pages %>
			<% if $CurrentBool %>
				$PageNum
			<% else %>
				<% if $Link %>
					<a href="/$Link">$PageNum</a>
				<% else %>
					...
				<% end_if %>
			<% end_if %>
		<% end_loop %>
		<% if $Results.NotLastPage %>
			<a class="next" href="/$Results.NextLink">Next</a>
		<% end_if %>
	<% end_if %>
	</p>
	<% if FullResultsLink %><p id="SearchEngineFullResultsLink"><a href="$FullResultsLink">View All Results</a></p><% end_if %>

<% else %>
	<p class="message warning">Sorry, no results could be found. Please try again.</p>
<% end_if %>
	$DebugHTML
</div>
