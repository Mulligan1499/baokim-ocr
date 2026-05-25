# Data Spec Variant Recipes

## V-A — Conceptual data model (ERD)

**When this variant:** Designing data for a new feature, documenting the data shape for stakeholders, or providing a conceptual model that dev/data engineer will physicalize.

**Audience:** Dev (will translate to physical schema), data engineer, product (understands what data the feature has), compliance (sensitivity check).

**Required additional sections:**
- Entity overview (list of all entities, 1-line each)
- ER diagram (Mermaid)
- Per-entity tables

**Variant-specific questions:**
1. New feature or refactor of existing?
2. Single bounded context or cross-module?
3. Any entities that already exist in the system we should reference, not redefine?
4. Multi-tenancy considerations? (data isolation across customers / partners)

**Variant-specific pitfalls:**
- Modeling system tables (audit_log, sessions, tokens) at conceptual level — usually those are implementation detail
- Confusing event with entity (Login event vs Session entity)
- Over-normalizing at conceptual level (splitting one business entity into 3 because dev mind anticipates DB normalization)
- Under-normalizing (one "everything" entity)
- Defining attributes without sensitivity classification

**Blind spots:**
- Multi-language data (product name in vi vs en)
- Effective dating (price now vs price-as-of-last-quarter)
- Soft delete / archive
- Cross-entity invariants (rules spanning entities)

**Typical length:** 1 ER diagram + 3-8 entity tables, 2-4 pages.

---

## V-B — Data dictionary

**When this variant:** Cataloging an existing system's data, often for: handoff to a new team, integration prep, audit, data governance.

**Audience:** Data governance team, integration partners, new team onboarding, compliance auditors.

**Required additional sections:**
- System / module being cataloged
- Per-entity attribute catalog
- Lifecycle per entity
- Usage / downstream dependencies per entity

**Variant-specific questions:**
1. Are we cataloging entire system or a specific module?
2. Source: where do we get the existing data definitions? (Code? Existing docs? SME interview?)
3. Is there a data governance framework / policy that defines required fields (owner, sensitivity)?
4. Who maintains this dictionary going forward?

**Variant-specific pitfalls:**
- Snapshotting without ownership chain (no one will keep it updated)
- Skipping sensitivity classification because "we'll do it later"
- Documenting fields but not their business meaning (a dictionary without meaning is just a list)
- Missing usage section — readers don't know what depends on the entity

**Blind spots:**
- "Hidden" attributes (system fields with business semantics — e.g., `is_vip` flag)
- Attributes whose meaning has drifted over time
- Reference data (codes / picklists) that need their own mini-dictionary
- Cross-system replicas of the same entity (which is the source of truth?)

**Typical length:** Catalog entries grow with system size; for one module, typically 5-15 pages.

---

## V-C — Data mapping

**When this variant:** Integration prep — defining how data flows between two systems. Often paired with `ba-feature-spec` Variant B (partner API) or Variant C (integration).

**Audience:** Dev on both sides of integration, data engineer, ops (will run the integration), the other system's owner.

**Required additional sections:**
- Source system summary (entities involved)
- Target system summary (entities involved)
- Mapping table (per entity, per attribute)
- Transformation rules (in business prose)
- Edge case handling matrix

**Variant-specific questions:**
1. Direction(s) — source → target only, or bidirectional?
2. Real-time, near-real-time, or batch?
3. Initial load required (backfill historical), or only forward?
4. Conflict resolution if bidirectional (who wins when both sides update)?
5. Tolerance for transformation loss (e.g., precision loss, encoding loss)?

**Variant-specific pitfalls:**
- Treating mapping as 1:1 when business semantics differ (same field name, different meaning)
- Ignoring edge cases (nulls, enum mismatches, precision) — that's where 80% of integration bugs live
- Missing reverse direction analysis when integration is bidirectional
- Not surfacing data ownership / trust boundary (when records conflict, whose wins?)

**Blind spots:**
- Reference data sync (picklists / codes on both sides must align over time)
- Multiplicity mismatch (1 source row → N target rows, or vice versa)
- Time / timezone handling
- Currency / unit conversion (if applicable)
- Soft delete propagation (deleted on source — what happens on target?)

**Typical length:** For an integration with ~5-10 entities, ~5-10 pages.

---

## When variants compose

For a partner API integration (paired with `ba-feature-spec` Variant B):
- **V-A** for our side's conceptual model (what entities the API exposes)
- **V-C** for mapping between partner's data and ours (if partner has different field structure)

For documenting an existing system before redesign:
- **V-B** for existing data dictionary
- Then **V-A** for new conceptual model (which entities we keep, change, retire)
- Then **V-C** for migration mapping (old → new)

Don't blend variants in one document. Each has different rigor expectations. Produce them as separate sections or separate documents.
