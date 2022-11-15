<div id="SearchEngineSearchResultsInner">
    <h2 class="searchResultsHeader">Search Results for <em>$SearchedFor</em></h2>

<% if Results %>
    <ul class="searchEngineResultsList">
        <% loop Results %>
        <li data-searchenginedataobjectid="$ID" class="searchEngineResultItem searchEngineItemFor$DataObjectClassName">
            <% if $Up.IsMoreDetailsResult %>$HTMLOutputMoreDetails<% else %>$HTMLOutput<% end_if %></li>
        <% end_loop %>
    </ul>
    <% if $Count > 20 %>
    <p id="SearchEngineResultsLinkHolder"><a id="SearchEngineResultsLink" href="$Link" data-items-per-page="$NumberOfItemsPerPage">$Count results</a></p>
    <% end_if %>
    <p class="searchEnginePageInfo">
    <% if $Results.MoreThanOnePage %>
        <% if $Results.NotFirstPage %>
            <a class="prev" href="$Results.PrevLink">‹</a>
        <% end_if %>
        <% loop $Results.Pages(7) %>
            <% if $CurrentBool %>
                <a class="current">$PageNum</a>
            <% else %>
                <% if $Link %>
                    <a href="$Link">$PageNum</a>
                <% else %>
                    ...
                <% end_if %>
            <% end_if %>
        <% end_loop %>
        <% if $Results.NotLastPage %>
            <a class="next" href="$Results.NextLink">›</a>
        <% end_if %>
    <% end_if %>
    </p>
    <% if FullResultsLink %><p id="SearchEngineFullResultsLink"><a href="$FullResultsLink">Full Results</a></p><% end_if %>

<% else %>
    <p class="message warning">Sorry, no results could be found. Please try again.</p>
<% end_if %>
    $DebugHTML
</div>
