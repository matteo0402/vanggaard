# Discogs API usage constraints

- Status: Accepted
- Date: 2026-08-07
- Jira: [SCRUM-11](https://vanggaard.atlassian.net/browse/SCRUM-11)
- Decision owners: Project maintainers

## Context

The MVP imports a Discogs collection into a local catalog so normal browsing does not depend on live Discogs requests. The Discogs API Terms of Use classify collection data and images as Restricted Data, prohibit commercial use of Restricted Data, limit how long API content may be stored and displayed, and prescribe attribution.

Discogs has not yet answered the project's support request about nightly synchronization, removed collection instances, cover images, or a future multi-user product. Until written clarification is received, the project will follow the conservative interpretation below.

This is a product and engineering constraint, not legal advice.

## Decision

### MVP scope

The Discogs-backed MVP is approved only as a private, single-user, non-commercial application for the owner of the connected Discogs account.

The application must use the official API with the account owner's authorization. It must not scrape Discogs, transfer Restricted Data to third parties, expose API credentials to the frontend, or use additional credentials to circumvent rate limits.

The application name and branding must not imply a Discogs partnership, sponsorship, or endorsement, and Discogs marks must not be its most prominent feature.

### Data classification

Discogs release titles, notes, dates, formats, track listings, barcodes and other identifiers, credits, third-party URLs, artist names and notes, label names and notes, and the associated release relationships explicitly listed in the Terms are treated as CC0 Data.

Discogs usernames, collection membership, collection folders, collection instance identifiers and fields, wantlists, marketplace data, images, and API fields not explicitly listed as CC0 Data are treated as Restricted Data. User-created notes, ratings, tags, storage locations, riddims, playlists, and DJ history must be stored separately from Discogs-derived data and remain owned by the application user.

If durable catalog data is later sourced from a Discogs monthly CC0 data dump instead of the API, that ingestion and its license obligations require a separate decision.

### Refresh and stale-data behavior

The synchronization scheduler must target a complete collection reconciliation at least every four hours. Release metadata used by the application must also be refreshed within that cycle or before it reaches the display-age limit.

Every locally stored Discogs resource must record when it was fetched and the canonical Discogs page that supplied the displayed data. The application must not display API-derived content once its last successful fetch is six hours old. Fetch age is the project's enforceable proxy for the Terms' requirement that displayed content not be more than six hours older than Discogs.

If a refresh cannot complete before that deadline, affected Discogs fields and images must be hidden rather than served stale. User-owned metadata may remain available when it can be presented without stale Discogs content.

Initial imports must remain unavailable for normal browsing until the imported content passes the same freshness rule. Synchronization must honor Discogs response headers and use a deployment-wide request budget with headroom below the documented authenticated limit.

### Retention and removal

API content may be retained only while it is necessary to provide the private collection service. If the service or API access is discontinued, API-derived content must be deleted.

A successful full collection reconciliation is authoritative for membership. Collection instances removed from Discogs must have their Restricted Data deleted during that reconciliation. Failed or partial reconciliations must not trigger deletion.

The application may retain user-owned notes, playlists, storage history, and DJ history after removal, but it must remove Discogs collection instance identifiers and other Restricted Data from those records. A remaining relationship may identify the corresponding CC0 release without asserting that it is still in the user's Discogs collection.

### Images

The MVP must not download, proxy, or create a durable local cache of Discogs images. It may store and display the image URLs returned by an authenticated API response only while the corresponding API resource is fresh.

Stale, expired, or unavailable images must be hidden. Image presentation must include the same adjacent attribution and direct Discogs source link as other API-derived data.

### Attribution and source links

The following notice must appear prominently in the application wherever Discogs API content is used:

> This application uses Discogs’ API but is not affiliated with, sponsored or endorsed by Discogs. ‘Discogs’ is a trademark of Zink Media, LLC.

Displayed API-derived data must also have the following notice directly adjacent to it:

> Data provided by Discogs.

That adjacent notice must link directly to the Discogs page containing the displayed data. The link must pass search-engine ranking credit and therefore must not use `nofollow` or another ranking-blocking mechanism. Security attributes such as `noopener` may still be used.

For release cards and release details, the link target is the canonical Discogs release page. Aggregate views must provide the closest relevant Discogs source link for each displayed data unit.

### Multi-user and commercial use

Launching a multi-user, paid, advertising-supported, or otherwise commercial Discogs-backed product is a no-go under this decision. Restricted Data must not be used commercially, and the currently proposed SaaS model must not proceed without express written permission from Discogs and a new project decision.

Discogs OAuth may be developed only after written guidance confirms the intended multi-user use. A technical OAuth implementation alone does not authorize a multi-user launch.

## Downstream requirements

Discogs integration work must enforce this decision rather than treating it as documentation only:

- API clients must send a unique identifying User-Agent and the Discogs API version media type.
- Stored Discogs resources must include fetch timestamps and canonical source URLs.
- Browsing queries or response resources must centrally suppress content at the six-hour boundary.
- Synchronization must target four-hour completion, respect one deployment-wide rate limit, and respond to Discogs rate-limit headers.
- Collection removals must be applied only after a successful full reconciliation and must purge Restricted Data.
- UI components must render the prescribed global and adjacent attribution.
- Automated tests must cover stale-data suppression, reconciliation removal, and attribution links when those features are implemented.

## Reassessment

This decision must be reviewed before the first Discogs-backed release, whenever Discogs changes its API Terms of Use, when Discogs answers the support request, and before any multi-user or commercial work begins.

A support response that permits broader caching or use does not change this policy automatically. The response must be retained as project evidence and this decision must be superseded explicitly.

## Sources

- [Discogs API Terms of Use](https://support.discogs.com/hc/en-us/articles/360009334593-API-Terms-of-Use), last updated 2025-05-27, reviewed 2026-08-07.
- [Discogs API documentation](https://www.discogs.com/developers/), reviewed 2026-08-07.
- [Discogs Application Name and Description Policy](https://support.discogs.com/hc/en-us/articles/360009207054-Application-Name-and-Description-Policy), linked by the API documentation and Terms, reviewed 2026-08-07.
