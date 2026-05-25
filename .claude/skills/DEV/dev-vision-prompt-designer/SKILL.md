---
name: dev-vision-prompt-designer
description: Thiết kế prompt Claude Vision API cho Stage 2 OCR Extractor — encode 7 anti-hallucination patterns + structured JSON output + negative few-shot. Output: system prompt + user template + schema + 4 test cases để validate trước merge.
when_to_use: |
  Triggers: "viết prompt vision cho [loại tài liệu]", "design prompt OCR cho [doc type]",
  "prompt extract key-value từ ảnh", "Claude Vision prompt", "anti-hallucination prompt",
  "structured output vision LLM", "prompt template cho Stage 2", "thiết kế prompt extraction
  từ ảnh".

  Anti-triggers (KHÔNG dùng skill này khi):
  - Prompt text-only (không có image input) → dùng generic prompt skill
  - OCR truyền thống Tesseract/PaddleOCR (không phải LLM-based)
  - Image generation (DALL-E/Stable Diffusion/Midjourney)
  - Review/debug prompt đã có → dùng prompt-reviewer skill (chưa build)
# Baokim enterprise extensions (không trong Anthropic spec):
owner: duy@baokim.vn
version: 0.2.0
lifecycle: active
domain: dev
created: 2026-05-15
updated: 2026-05-25
tags: [vision, llm, prompt-engineering, ocr, anti-hallucination, structured-output, claude-api, baokim]
---

# Vision Prompt Designer

## Mục đích

Giúp dev Baokim thiết kế prompt cho Claude Vision API đạt 2 tiêu chí song song:

1. **Extraction accuracy cao** trên 15 loại tài liệu KSNB + 3 ngôn ngữ (VN/EN/ZH)
2. **Anti-hallucination strict** — AI thà trả `value=""` còn hơn bịa số CCCD, ngày tháng, số tiền

Skill encode tacit knowledge về:
- 7 anti-hallucination patterns đã battle-tested
- Structured output JSON schema design cho extraction tasks
- Few-shot example selection (positive + negative, negative là bắt buộc)
- Refusal patterns khi input không phải tài liệu

**Reference foundation**:
- `02-tech-decisions.md` Section 4 (Stage 2: Vision Extractor)
- `02-tech-decisions.md` Section 5 (15 document types + 1 fallback `other`)
- `02-tech-decisions.md` Section 10 (Confidence strategy — multi-signal)

## Khi nào dùng skill này

✅ Dev cần viết prompt mới cho Stage 2 Vision Extractor
✅ Dev cần adapt prompt hiện có cho doc type mới (vd: thêm hợp đồng lao động)
✅ Dev paste prompt hiện có và hỏi "có đủ anti-hallucination chưa"
✅ Khi BA/QA report case AI bịa thông tin → cần refine prompt
✅ Khi build skill runtime `vn-document-classifier` (Stage 1) — chia sẻ pattern với skill này

❌ Prompt text-only (không có image input)
❌ OCR truyền thống Tesseract/PaddleOCR — không phải LLM-based
❌ Image generation (DALL-E, Stable Diffusion, Midjourney)
❌ Review/debug prompt đã viết — dùng `prompt-reviewer` skill (chưa build)

## Workflow

### Step 1: Hiểu yêu cầu extraction

Trước khi design prompt, xác định 5 thông tin. Nếu user chưa cung cấp, hỏi tối đa 2 câu critical:

1. **Document type**: 1 trong 15 loại enum theo `config/ocr_doc_taxonomy.php`:
   `cccd, national_id_foreign, passport, gpkd, contract_vi, contract_en, contract_zh, invoice, legal_doc, customs_declaration, bill_of_lading, aml_charter, power_of_attorney, labor_contract, financial_report` + fallback `other`. Có thể `auto` cho generic.

2. **Critical fields** (weight 2 trong confidence aggregator): danh sách field KHÔNG được phép sai. Defaults theo doc type (lấy từ `config/ocr_doc_taxonomy.php` — single source of truth):
   - `cccd`: so_cccd, ho_ten, ngay_sinh, ngay_cap
   - `passport`: passport_number, full_name, date_of_birth, expiry_date, nationality
   - `national_id_foreign`: id_number, full_name, name, date_of_birth, nationality
   - `gpkd`: mst, ten_doanh_nghiep, dia_chi, nguoi_dai_dien, ngay_cap
   - `contract_vi`: ben_a, ben_b, ngay_ky, gia_tri
   - `contract_en`: party_a, party_b, signing_date, value
   - `contract_zh`: party_a, party_b, signing_date, value (Hán-Việt phonetic vào value_translated_vi)
   - `invoice`: invoice_number, total_amount, invoice_date, mst
   - `bill_of_lading`: bl_number, shipper, consignee, vessel_name, container_number
   - `customs_declaration`: declaration_number, declaration_date, importer, exporter, hs_code
   - `labor_contract`, `aml_charter`, `power_of_attorney`, `legal_doc`, `financial_report`: xem config

3. **Normal fields** (weight 1): các field bổ sung. Sai vẫn chấp nhận được.

4. **Language**: `vi` / `en` / `zh` / `mixed` / `auto`. Nếu `auto` → prompt yêu cầu detect.

5. **Translation needed**: true/false. Nếu true → output có thêm `value_translated_vi` per text field (KHÔNG dịch numbers/codes).

### Step 2: Apply 7 anti-hallucination patterns

7 patterns BẮT BUỘC encode trong prompt. Skip 1 trong 7 → skill output warning + đề xuất bổ sung.

**Pattern AH-1: Empty value instead of guess**
```
CRITICAL RULE: If you cannot clearly read a value from the image, return value=""
and confidence=0. Do NOT invent, guess, or interpolate. Returning empty is ALWAYS
better than returning wrong information.
```

**Pattern AH-2: Per-field confidence calibration**
```
For each extracted field, provide confidence ∈ [0.0, 1.0]:
- 0.9-1.0: text fully visible, unambiguous, no doubt
- 0.7-0.9: text visible but some characters could be confused (e.g., 0 vs O, 1 vs l)
- 0.5-0.7: text partially visible, can infer most but not all characters
- 0.3-0.5: text mostly obscured, can guess but not verify
- 0.0-0.3: text illegible — in this case return value="" instead
```

**Pattern AH-3: Refusal pattern for non-documents**
```
First, assess: Is this image a document (formal paper, ID card, form, receipt, etc.)?
If NO (e.g., landscape photo, meme, screenshot of chat, blank page, art image):
  Return: {"document_detected": false, "reason": "[brief explanation]"}
  Do NOT extract any fields.
If YES, proceed with extraction.
```

**Pattern AH-4: Reasoning trace before extraction**
```
Before listing extracted fields, describe in 1-2 sentences:
- What type of document this appears to be
- Overall image quality (clear / partial / blurry / damaged)
- Any obvious obstructions (glare, fingers, watermark, tear)
This forces grounded observation, reduces hallucination.
```

**Pattern AH-5: Critical field validation flag**
```
For critical fields (id_number, dates, amounts):
- Cross-check: does the value pass basic format validation?
  (CCCD must be 12 digits, dates must be valid calendar dates, amounts must have currency)
- If validation FAILS but you read it that way → set flagged_low_confidence=true
- Do NOT auto-correct. Just flag. Stage 3 Validator will handle correction logic.
```

**Pattern AH-6: Translation grounding** (chỉ khi translate=true)
```
When providing value_translated_vi:
- ONLY translate text you actually extracted from the image
- Do NOT add interpretation, elaboration, or fill-in-the-blanks
- If source text is unclear (confidence < 0.5), translation should be "" too
- For Chinese names, provide BOTH original characters AND Vietnamese phonetic (Hán-Việt)
- Do NOT translate numbers, codes, IDs (those stay as-is)
```

**Pattern AH-7: Negative few-shot example (CORE)**
```
At least 1 example in the prompt MUST show the model correctly returning
value="" or document_detected=false. This anchors the refusal behavior.
Models that only see positive examples are dramatically more likely to hallucinate.
```

→ **Pattern AH-7 là single point of failure.** Bỏ qua = anti-hallucination strategy thất bại.

### Step 3: Generate prompt với 4 components

Output cấu trúc:

1. **System Prompt**: persona + global rules + 7 patterns AH
2. **User Prompt Template**: per-call, có `{variables}` thay thế runtime
3. **Output Schema**: JSON schema cho structured output
4. **Few-shot Examples**: tối thiểu 1 positive + 1 negative (negative bắt buộc)

Reference templates ở `## Templates` section bên dưới.

### Step 4: Validate prompt với 4 test cases

Trước khi merge prompt vào code Laravel, dev BẮT BUỘC test với 4 cases:

| Test | Input | Expected |
|---|---|---|
| Happy path | Tài liệu rõ nét đúng doc type | High confidence + đầy đủ fields |
| Blurry/partial | Ảnh mờ hoặc thiếu góc | Low confidence + một số fields="" |
| Wrong doc type | User claim id_card nhưng upload hợp đồng | Extract theo thực tế, không ép theo claim |
| Non-document | Upload ảnh phong cảnh / meme | document_detected=false, không extract |

Nếu fail test 4 → quay lại Step 2, gia cố AH-3 + AH-7.

## Templates & Examples (lazy-load references)

Templates + 4 anchor examples ở reference files riêng để giữ SKILL.md ngắn (Progressive Disclosure pattern của Anthropic).

- **Templates skeleton** (System Prompt + User Prompt + Output Schema): xem [references/templates.md](references/templates.md)
- **4 anchor examples** (CCCD VN happy/negative, Bill of Lading ZH, ảnh mờ regression, CV reusability): xem [references/examples.md](references/examples.md)

Đọc references **khi cần adapt prompt cho doc type cụ thể**. Cho workflow + 7 anti-hallucination patterns (CRITICAL) → đã có trong SKILL.md này, không cần đọc thêm.

## What NOT to do

❌ KHÔNG hardcode 15 doc types vào skill body — taxonomy tách references/ tuần 3 (Option A workflow)
❌ KHÔNG đề xuất prompt thiếu pattern AH-3 (refusal) — non-document case là edge case bị missed nhiều nhất
❌ KHÔNG output prompt thiếu negative few-shot — anchor refusal behavior là CORE
❌ KHÔNG suggest prompt tự correct/validate (vd: "if id_number is 11 digits, prepend 0"). Để Stage 3 Validator làm, không phải Stage 2 Generator
❌ KHÔNG include personal data thật trong few-shot examples — dùng anonymized hoặc fictional (vd: "NGUYỄN VĂN A", "001234567890")
❌ KHÔNG output prompt quá dài (> ~2000 tokens) — Claude Vision có context limit, cost tăng theo length
❌ KHÔNG mix output language (đừng có chỗ tiếng Anh chỗ tiếng Việt trong cùng JSON output) — confuse downstream parser

## Cost-aware notes

Vision API pricing reference (Claude Sonnet 4.6, tháng 5/2026):
- Image input: ~$0.004-0.01 per image tùy resolution
- Output tokens: $15/M output

Prompt design tips để giảm cost:
- **Prompt caching**: phần system prompt + few-shot examples reuse → cache hits cost 0.1x base input. Áp dụng cho mọi doc type cùng template
- **Image resolution**: scale ảnh xuống 1024x1024 trước khi gửi → giảm tokens ~40% không mất accuracy cho hầu hết doc types KSNB. Exception: tờ khai HQ chữ nhỏ → giữ 1568x1568
- **Output length**: chỉ request JSON, không request reasoning trail dài → giảm output tokens. `image_quality_note` giới hạn 1-2 sentences

## Maintenance

- Update khi: Anthropic release Vision API features mới (vd: bbox grounding native, structured output mode)
- Update khi: phát hiện new anti-hallucination pattern qua QA testing
- Update khi: thêm doc type mới vào support list (chỉ cần update Step 1 critical fields defaults)
- Version bump: minor (0.1.x) cho add pattern/example, major (0.x.0) cho breaking change schema
- Test prompts: xem `tests/prompts.md` — target 10+ trước active lifecycle

## Roadmap (refactor tuần 3 — Option A)

Inline trong v0.1.0 (sẽ split khi sang v0.2.0):
- 7 anti-hallucination patterns (AH-1 đến AH-7)
- 15 doc types taxonomy + critical_fields defaults
- Templates: System Prompt, User Prompt, Output Schema

Tuần 3 plan tách:
- `references/anti-hallucination-patterns.md` — 7 patterns + rationale + examples
- `references/taxonomies/ksnb.md` — 15 loại + critical fields KSNB
- `references/taxonomies/hr.md` — domain HR cho reusability demo
- `references/prompt-templates/` — per-doc-type System Prompt specialized
- `references/cost-optimization.md` — caching strategy + image preprocessing
