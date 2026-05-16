---
name: dev-vision-prompt-designer
description: |
  Skill thiết kế prompt cho Claude Vision API trong Stage 2 Vision Extractor (harness 7 stages của dự án OCR Baokim KSNB). Dùng khi cần viết hoặc adapt prompt OCR + extraction từ ảnh/PDF tài liệu. Đầu vào: doc type + danh sách critical fields + language hint + optional sample image. Đầu ra: system prompt + user prompt template + JSON output schema + 1 positive + 1 negative few-shot example + anti-hallucination checklist + 4 test cases để validate. Encode tacit knowledge về 7 anti-hallucination patterns (empty value instead of guess, per-field confidence calibration, refusal pattern cho non-document, reasoning trace trước extraction, critical field validation, translation grounding, negative few-shot).
  Triggers: "viết prompt vision cho [loại tài liệu]", "design prompt OCR cho...", "prompt extract key-value từ ảnh", "Claude Vision prompt cho [loại tài liệu]", "anti-hallucination prompt", "structured output vision LLM", "prompt template cho Stage 2", "thiết kế prompt extraction".
  Anti-triggers (KHÔNG dùng skill này khi): user cần text-only prompt không có image input (dùng generic prompt skill), user setup OCR truyền thống Tesseract/PaddleOCR (đây là LLM-based), user cần image generation prompt (DALL-E/Stable Diffusion), user paste prompt và xin review/debug (dùng prompt-reviewer skill — chưa build).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: dev
created: 2026-05-15
updated: 2026-05-15
tags: [vision, llm, prompt-engineering, ocr, anti-hallucination, structured-output, claude-api, baokim]
---

# Vision Prompt Designer

## Mục đích

Giúp dev Baokim thiết kế prompt cho Claude Vision API đạt 2 tiêu chí song song:

1. **Extraction accuracy cao** trên 13 loại tài liệu KSNB + 3 ngôn ngữ (VN/EN/ZH)
2. **Anti-hallucination strict** — AI thà trả `value=""` còn hơn bịa số CCCD, ngày tháng, số tiền

Skill encode tacit knowledge về:
- 7 anti-hallucination patterns đã battle-tested
- Structured output JSON schema design cho extraction tasks
- Few-shot example selection (positive + negative, negative là bắt buộc)
- Refusal patterns khi input không phải tài liệu

**Reference foundation**:
- `02-tech-decisions.md` Section 4 (Stage 2: Vision Extractor)
- `02-tech-decisions.md` Section 5 (13 document types)
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

1. **Document type**: 1 trong 13 loại enum (id_card, passport, business_license, contract_vi, contract_foreign, invoice, bank_statement, legal_document, customs_declaration, bill_of_lading, company_charter, power_of_attorney, employment_contract) hoặc `auto` (generic fallback).

2. **Critical fields** (weight 2 trong confidence aggregator): danh sách field KHÔNG được phép sai. Defaults theo doc type:
   - `id_card`: id_number, full_name, date_of_birth, date_of_issue
   - `passport`: passport_number, full_name, date_of_birth, expiry_date, nationality
   - `invoice`: invoice_number, total_amount, invoice_date, tax_code
   - `contract_*`: party_a_name, party_b_name, signing_date, contract_value
   - `bill_of_lading`: bl_number, shipper, consignee, vessel_name, container_number
   - `customs_declaration`: declaration_number, declaration_date, importer, exporter, hs_code

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

## Templates

### System Prompt template

```
You are an OCR and information extraction assistant for Baokim's internal compliance (KSNB) workflow.
Your task: extract structured data from document images for staff review.

CRITICAL RULES (in priority order):
1. If image is NOT a document → return {"document_detected": false, "reason": "..."}
2. If you cannot read a value clearly → return value="" and confidence=0
3. Never invent, guess, or interpolate values. Empty is better than wrong.
4. Per-field confidence ∈ [0, 1], calibrated per the scale below
5. Critical fields require flagged_low_confidence=true when format validation fails
6. For translation, only translate what you actually extracted
7. Output strictly the JSON schema. No commentary outside JSON.

Confidence calibration scale:
- 0.9-1.0: fully visible, unambiguous
- 0.7-0.9: visible but ambiguous chars (0/O, 1/l, B/8)
- 0.5-0.7: partially visible, partial inference
- 0.3-0.5: mostly obscured, low certainty
- 0.0-0.3: illegible — return value="" instead

Document type expected: {doc_type}
Critical fields (weight 2): {critical_fields_list}
Normal fields (weight 1): {normal_fields_list}
{translation_instruction}

Workflow:
1. First, in 1-2 sentences, describe document type and image quality
2. Then extract fields matching the schema
3. Provide per-field confidence
4. Flag critical fields failing format validation
```

### User Prompt template

```
[Image attached]

Please analyze the attached document image:
1. Confirm document type and image quality (1-2 sentences in image_quality_note)
2. Extract all fields matching the JSON schema
3. Provide per-field confidence per the calibration scale
{if translate=true: 4. Translate text fields to Vietnamese in value_translated_vi}

Return ONLY valid JSON matching the schema. No additional commentary outside JSON.
```

### Output Schema (JSON)

```json
{
  "document_detected": true,
  "reason_if_not_detected": "",
  "document_type_actual": "id_card",
  "language_detected": "vi",
  "image_quality_note": "Clear front-side CCCD, no obstructions",
  "raw_text": "...",
  "key_values": [
    {
      "key": "id_number",
      "value": "001234567890",
      "value_translated_vi": null,
      "confidence": 0.95,
      "critical": true,
      "flagged_low_confidence": false,
      "validation_passed": true
    }
  ],
  "overall_confidence_self_report": 0.92,
  "warnings_self_report": []
}
```

## Examples

### Example 1: CCCD tiếng Việt (happy path)

**Input to skill**:
- doc_type: id_card
- critical_fields: id_number, full_name, date_of_birth, date_of_issue
- language: vi
- translate: false

**Skill output (excerpt)**:

System Prompt (specialized):
```
[base system prompt above]

Document type: Vietnamese CCCD/CMND (Citizen ID Card)
Critical fields: id_number (must be 12 digits), full_name, date_of_birth (DD/MM/YYYY), date_of_issue (DD/MM/YYYY)
Normal fields: nationality, ethnicity, religion, place_of_origin, place_of_residence, expiry_date, gender

Specific validation:
- id_number: exactly 12 digits, no letters
- Dates: valid calendar date, not in future, not before 1900
```

Positive few-shot:
```json
{
  "document_detected": true,
  "image_quality_note": "Clear front-side CCCD, glossy surface, no obstructions",
  "key_values": [
    {"key": "id_number", "value": "001234567890", "confidence": 0.97, "critical": true, "validation_passed": true},
    {"key": "full_name", "value": "NGUYỄN VĂN A", "confidence": 0.95, "critical": true, "validation_passed": true},
    {"key": "date_of_birth", "value": "01/01/1990", "confidence": 0.93, "critical": true, "validation_passed": true}
  ]
}
```

Negative few-shot (CRITICAL):
```json
{
  "document_detected": true,
  "image_quality_note": "CCCD with smudge over id_number digits 4-8",
  "key_values": [
    {"key": "id_number", "value": "", "confidence": 0, "critical": true, "flagged_low_confidence": true, "validation_passed": false},
    {"key": "full_name", "value": "NGUYỄN VĂN A", "confidence": 0.9, "critical": true, "validation_passed": true}
  ]
}
```

→ Lưu ý: id_number trả `""` thay vì cố đoán. Đây là behavior expected, **không phải bug**.

### Example 2: Vận đơn tiếng Trung (translation + critical fields)

**Input to skill**:
- doc_type: bill_of_lading
- critical_fields: bl_number, shipper, consignee, vessel_name, container_number
- language: zh
- translate: true

**Skill output (excerpt)**:

System Prompt thêm:
```
Document type: Bill of Lading (Chinese export shipping document)
This document is in Chinese (Simplified). Extract original Chinese values, then translate text fields to Vietnamese.

Translation rules:
- Shipper/consignee names: value = original Chinese (e.g., "上海贸易有限公司")
                          value_translated_vi = Vietnamese phonetic + meaning (e.g., "Công ty TNHH Thương mại Thượng Hải")
- Vessel name: same as shipper (translate)
- bl_number, container_number: value = original alphanumeric (e.g., "MSCU1234567")
                               value_translated_vi = null  ← KHÔNG dịch numbers/codes
- Addresses: translate freely
- Dates: convert to DD/MM/YYYY format in value_translated_vi
```

→ Tránh AI dịch sai số container thành chữ Việt — một lỗi hay gặp khi prompt không phân biệt.

### Example 3: Ảnh mờ → anti-hallucination kick in (regression test case)

**Input to skill**: doc_type=id_card, ảnh CCCD bị che vùng id_number

**Skill behavior expected**:
- Prompt generated phải có Example 1 negative (ở trên) làm few-shot
- Khi runtime gặp ảnh tương tự → model trained-by-context biết trả `value=""`
- Nếu skill output prompt thiếu negative example → output warning rõ ràng cho dev

→ Đây là regression test case bắt buộc trong `tests/prompts.md`.

### Example 4: Reusability — adapt cho CV (HR domain, ngoài 13 loại KSNB)

**Input to skill**:
- doc_type: cv (mới, không có trong 13 loại enum)
- critical_fields: candidate_name, email, phone, current_company, years_experience
- normal_fields: education, skills, languages
- language: vi
- translate: false

**Skill output**: vẫn hoạt động! Skill này KHÔNG hardcode 13 doc types KSNB. Workflow + 7 patterns AH chạy cho bất kỳ doc type nào dev truyền vào.

System Prompt generated cho CV:
```
[base system prompt]

Document type: CV/Resume (Vietnamese)
Critical fields: candidate_name, email (must match email regex), phone (10-11 digits Vietnamese mobile), current_company, years_experience (integer)
Normal fields: education_summary, skills, languages, certifications, hobbies

Specific validation:
- email: regex /^[^@]+@[^@]+\.[^@]+$/
- phone: Vietnamese mobile format starting 03/05/07/08/09 + 8 digits
- years_experience: 0-50 integer
```

→ Đây là reusability angle. Tuần 3 Option A refactor: tách taxonomy 13 loại KSNB ra `references/taxonomies/ksnb.md`, thêm `references/taxonomies/hr.md` với cv/application_letter/employment_contract, skill hoạt động không sửa.

## What NOT to do

❌ KHÔNG hardcode 13 doc types vào skill body — taxonomy tách references/ tuần 3 (Option A workflow)
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
- 13 doc types taxonomy + critical_fields defaults
- Templates: System Prompt, User Prompt, Output Schema

Tuần 3 plan tách:
- `references/anti-hallucination-patterns.md` — 7 patterns + rationale + examples
- `references/taxonomies/ksnb.md` — 13 loại + critical fields KSNB
- `references/taxonomies/hr.md` — domain HR cho reusability demo
- `references/prompt-templates/` — per-doc-type System Prompt specialized
- `references/cost-optimization.md` — caching strategy + image preprocessing
