# Task 07 — Graph Exporter

## Goal
Restore the graph exporter module that transforms normalized works/authors into graph structures consumable by `mbsoft31/graph-core` and `mbsoft31/graph-algorithms`.

## Outputs
- `src/Exporter/Graph/GraphExporter.php`
- `src/Exporter/Graph/Adapters/AlgorithmsHelper.php`

## Instructions for Codex
1. **GraphExporter responsibilities**
   - Accept dependencies: `ScholarlyDataSource $dataSource`, optional cache, optional logger.
   - Provide methods:
     - `buildWorkCitationGraph(array $workIds, Query $q): Graph` — nodes for works, edges representing citations (u→v meaning u cites v).
     - `buildAuthorCollaborationGraph(array $authorIds, Query $q): Graph` — nodes for authors, edges weighted by co-authorship counts.
     - `exportToArray(Graph $graph): array` and `exportToJson(Graph $graph): string` for serialization.
     - Helpers for running algorithms (delegate to `AlgorithmsHelper`).
   - Use normalized data from adapters; ensure unique node IDs via `Identity::ns`.
2. **AlgorithmsHelper**
   - Provide wrappers around `GraphAlgorithms` for PageRank, betweenness centrality, community detection.
   - Methods return associative arrays keyed by node ID.
   - Handle directed vs undirected graphs based on algorithm semantics.
3. **Graph construction details**
   - Use `GraphBuilder` (from `graph-core`) to create graphs.
   - For works graph: fetch each work (batch where available) then call `listReferences`/`listCitations` as needed; respect `Query::$limit` to avoid runaway requests.
   - For collaboration graph: fetch works authored by specified authors, build adjacency weighted by joint papers; optional threshold parameter for edge inclusion.
   - Provide hooks to inject progress callback for long-running exports (callable parameter default null).
4. **Performance considerations**
   - Use caching to avoid duplicate API calls when the same work appears multiple times.
   - Allow chunk processing to avoid hitting rate limits; pause respecting `rateLimitState()` when provided.
5. **Documentation**
   - Annotate methods with PHPDoc showing returned structures and usage examples.

## Acceptance Criteria
- Graph builder passes unit tests constructing simple networks (use fixture data).
- Algorithms helper returns deterministic arrays for seeded graphs.
- Exporter gracefully handles missing works/authors (skip with warning log).
- Supports serialization without circular references or resource leaks.
