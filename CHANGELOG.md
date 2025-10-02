# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]
- 

## [1.0.0] - 2025-10-01
- Rebuilt Scholarly Providers package targeting PHP 8.2 with PSR-compliant tooling.
- Added adapters for OpenAlex, Semantic Scholar Graph (S2), and Crossref with retries, caching, and normalization parity.
- Implemented graph exporter for citation and collaboration analytics plus helper access to PageRank/Betweenness algorithms.
- Delivered Laravel service provider, facade, configuration publishing, and documentation.
- Recreated Pest test suites covering contracts, core utilities, adapters, exporter, and factory wiring; `composer quality` ready.
