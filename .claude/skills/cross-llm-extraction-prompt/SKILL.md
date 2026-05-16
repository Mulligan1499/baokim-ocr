---
name: cross-llm-extraction-prompt
description: |
  Foundation skill thiết kế prompt cho mọi LLM extraction task (text input hoặc vision input). Encode 7 anti-hallucination patterns + structured output JSON schema design + few-shot example selection. Cross-domain reusable: OCR document parsing, CV/resume parsing, contract clause extraction, customer feedback structured extraction, email triage, log/error analysis, meeting transcript → action items. Đầu vào: extraction contract (input type, output schema, critical fields, language, refusal cases). Đầu ra: system prompt + user prompt template + JSON output schema + 1 positive + 1 negative few-shot example + anti-hallucination checklist + 4 test cases. Specialized skills (dev-vision-prompt-designer, ba-cv-parser, legal-clause-extractor...) inherit foundation từ skill này.
  Triggers: "thiết kế prompt extraction", "anti-hallucination prompt", "structured output prompt LLM", "prompt cho LLM extract data từ...", "prompt extract structured data", "extraction prompt template", "prompt cho [task] cần JSON output".
  Anti-triggers (KHÔNG dùng skill này khi): conversational chatbot prompt (không phải extraction), code generation prompt (dùng dev-* skills), pure translation prompt (không có structured extraction), image generation prompt (DALL-E/MJ), prompt review/debug (dùng prompt-reviewer).
owner: duy@baokim.vn
version: 0.1.0
lifecycle: draft
domain: cross
created: 2026-05-15
updated: 2026-05-15
tags: [cross, llm, prompt-engineering, anti-hallucination, structured-output, foundation, baokim]
---

# LLM Extraction Prompt Designer (Foundation)

## Mục đích

Foundation skill cho mọi prompt extraction task tại Baokim. Khác với specialized skill: skill này **KHÔNG hardcode** doc type, taxonomy, hay critical fields. Caller truyền vào extraction contract, skill generate prompt theo 7 anti-hallucination patterns.

Specialized skills inherit từ đây:
- `dev-vision-prompt-designer` (vision OCR specific)
- `ba-cv-parser` (CV/resume parsing — TBD)
- `legal-clause-extractor` (Pháp chế — TBD)
- `cross-feedback-analyzer` (Sales feedback structured — TBD)

Tất cả chỉ thêm: domain taxonomy + critical fields defaults + domain-specific examples. 7 patterns AH inherit unchanged.

## Khi nào dùng skill này

✅ Bất kỳ task nào yêu cầu LLM extract structured data từ unstructured input
✅ Cần JSON output với schema xác định
✅ Có per-field confidence requirement
✅ Có refusal case (input ngoài scope)
✅ Cần few-shot examples để anchor behavior

❌ Conversational chatbot (không phải extraction)
❌ Code generation (dùng skill `dev-*`)
❌ Translation only (không có schema extraction)
❌ Image generation
❌ Prompt review/debug existing prompt (dùng `prompt-reviewer` — TBD)

## Workflow

### Step 1: Identify extraction contract

5 thông tin cần biết trước khi design (hỏi tối đa 2 câu critical nếu user chưa cung cấp):

1. **Input type**: `text` / `image` / `mixed`. Quyết định template prompt format.
2. **Output schema**: JSON shape mong muốn + danh sách critical fields (weight 2 — không được phép sai) + normal fields (weight 1).
3. **Source language(s)**: `vi` / `en` / `zh` / `mixed` / `auto`.
4. **Translation required?**: `true` / `false`. Nếu true → schema thêm `value_translated_vi` per text field.
5. **Refusal cases**: liệt kê input nào KHÔNG nên extract (vd: vision OCR refuse khi không phải document; CV parser refuse khi không phải CV).

### Step 2: Apply 7 anti-hallucination patterns

7 patterns BẮT BUỘC encode trong prompt. Bỏ pattern nào → skill output warning + đề xuất bổ sung.

**Pattern AH-1: Empty value instead of guess**
```
CRITICAL RULE: If you cannot extract a value with reasonable confidence, return value=""
and confidence=0. Do NOT invent, guess, or interpolate. Returning empty is ALWAYS
better than returning wrong information.
```

**Pattern AH-2: Per-field confidence calibration**
```
For each extracted field, provide confidence ∈ [0.0, 1.0]:
- 0.9-1.0: clearly extractable, unambiguous source
- 0.7-0.9: extractable but source has minor ambiguity
- 0.5-0.7: partial extraction, some inference needed
- 0.3-0.5: heavy inference, low certainty
- 0.0-0.3: cannot extract — return value="" instead
```

**Pattern AH-3: Refusal pattern for out-of-scope input**
```
First, assess: Does this input match the expected scope ({scope_description})?
If NO: Return {"in_scope": false, "reason": "[brief explanation]"}. Extract nothing.
If YES: Proceed with extraction.
```

**Pattern AH-4: Reasoning trace before extraction**
```
Before listing extracted fields, describe in 1-2 sentences:
- What the input appears to be
- Quality/completeness of the input
- Any obvious anomalies
This forces grounded observation, reduces hallucination.
```

**Pattern AH-5: Critical field validation flag**
```
For critical fields, cross-check format validation:
- Set flagged_low_confidence=true if format/regex fails but you read it that way
- Do NOT auto-correct. Just flag. Downstream validators handle correction.
```

**Pattern AH-6: Translation grounding** (chỉ khi translate=true)
```
When providing translation:
- ONLY translate text actually extracted
- Do NOT add interpretation or fill-in-the-blanks
- If source unclear (confidence < 0.5), translation should be "" too
- For language-specific names (Chinese, Korean, Japanese): provide BOTH original + phonetic
- Numbers, codes, IDs: keep as-is, do NOT translate
```

**Pattern AH-7: Negative few-shot example (SINGLE POINT OF FAILURE)**
```
At least 1 example MUST show the model correctly returning value="" or in_scope=false.
Models trained only on positive examples hallucinate dramatically more.
This is non-negotiable.
```

### Step 3: Generate 4 prompt components

1. **System Prompt**: persona + global rules + 7 patterns AH inline
2. **User Prompt Template**: per-call với `{variables}`
3. **Output Schema (JSON)**: structured shape
4. **Few-shot Examples**: tối thiểu 1 positive + 1 negative (negative bắt buộc)

Templates ở `## Templates` bên dưới.

### Step 4: Validate với 4 test cases

| Test | Input | Expected |
|---|---|---|
| Happy path | Input rõ ràng, in scope | High confidence, đầy đủ fields |
| Partial input | Input thiếu 1 phần | Một số fields="" + low confidence |
| Out-of-scope | Input không match scope | `in_scope=false` + lý do |
| Critical validation fail | Input có critical field format sai | `flagged_low_confidence=true`, không auto-correct |

Fail test 3 → quay lại Step 2, gia cố AH-3 + AH-7.

## Templates

### System Prompt template (generic)

```
You are a structured information extraction assistant.
Your task: extract data from input matching the schema, following anti-hallucination rules strictly.

CRITICAL RULES (priority order):
1. If input is out of scope → return {"in_scope": false, "reason": "..."}
2. If a value cannot be extracted clearly → return value="" and confidence=0
3. Never invent, guess, or interpolate. Empty is better than wrong.
4. Per-field confidence ∈ [0, 1], calibrated per the scale below
5. Critical fields require flagged_low_confidence=true when format validation fails
6. Output strictly the JSON schema. No commentary outside JSON.

Confidence calibration:
- 0.9-1.0: clear, unambiguous
- 0.7-0.9: clear with minor ambiguity
- 0.5-0.7: partial, some inference
- 0.3-0.5: heavy inference
- 0.0-0.3: cannot extract → return value="" instead

Extraction contract:
- Expected scope: {scope_description}
- Critical fields (weight 2): {critical_fields}
- Normal fields (weight 1): {normal_fields}
- Source language: {language}
{translation_block}

Workflow:
1. Describe input quality in 1-2 sentences (reasoning_trace field)
2. Extract fields per schema
3. Provide per-field confidence
4. Flag critical fields failing validation
```

### User Prompt template

```
{input_block}

Please analyze the above input and return JSON matching the schema.
{translation_instruction}
Return ONLY valid JSON. No commentary outside JSON.
```

### Output Schema (generic shape)

```json
{
  "in_scope": true,
  "reason_if_not_in_scope": "",
  "reasoning_trace": "Input appears to be [type], quality [assessment].",
  "language_detected": "vi",
  "key_values": [
    {
      "key": "field_name",
      "value": "extracted_value",
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

### Example 1: OCR vision (foundation cho dev-vision-prompt-designer)

**Extraction contract**:
- Input type: image
- Scope: "Vietnamese ID card (CCCD/CMND)"
- Critical fields: id_number, full_name, date_of_birth, date_of_issue
- Language: vi
- Translation: false
- Refusal: non-document images

**Skill output** (system prompt addition):
```
Expected scope: Vietnamese CCCD/CMND. If image is not a CCCD (e.g., passport, landscape photo, blank page), return in_scope=false.

Specific validation:
- id_number: exactly 12 digits
- Dates: DD/MM/YYYY, valid calendar, not future
```

Negative few-shot:
```json
{
  "in_scope": true,
  "reasoning_trace": "CCCD image with smudge over id_number digits 4-8.",
  "key_values": [
    {"key": "id_number", "value": "", "confidence": 0, "critical": true, "flagged_low_confidence": true, "validation_passed": false},
    {"key": "full_name", "value": "NGUYỄN VĂN A", "confidence": 0.9, "critical": true, "validation_passed": true}
  ]
}
```

### Example 2: CV/Resume parsing (text-based, HR domain)

**Extraction contract**:
- Input type: text (CV plaintext or extracted from PDF/docx)
- Scope: "Vietnamese CV/resume of job candidate"
- Critical fields: candidate_name, email, phone, current_position, years_experience
- Normal fields: education, skills, certifications, languages
- Language: vi
- Translation: false
- Refusal: non-CV text (e.g., cover letter alone, job description, random text)

**Skill output** (system prompt addition):
```
Expected scope: Vietnamese job CV/resume. If text appears to be a cover letter, job posting, or unrelated content, return in_scope=false.

Specific validation:
- email: regex /^[^@]+@[^@]+\.[^@]+$/
- phone: Vietnamese mobile starting 03/05/07/08/09 + 8 digits
- years_experience: 0-50 integer
```

Few-shot positive:
```json
{
  "in_scope": true,
  "reasoning_trace": "Standard Vietnamese CV with sections: thông tin cá nhân, kinh nghiệm, học vấn, kỹ năng.",
  "key_values": [
    {"key": "candidate_name", "value": "TRẦN THỊ B", "confidence": 0.98, "critical": true},
    {"key": "email", "value": "tran.b@example.com", "confidence": 0.99, "critical": true, "validation_passed": true},
    {"key": "years_experience", "value": "5", "confidence": 0.85, "critical": true, "validation_passed": true}
  ]
}
```

→ Demo reusability: cùng skill, khác domain. HR team dùng được tuần 4 cuộc thi sau khi skill stable.

### Example 3: Customer feedback structured extraction (text-based, Sales/CS domain)

**Extraction contract**:
- Input type: text (feedback email / chat transcript)
- Scope: "Customer feedback about Baokim payment service"
- Critical fields: sentiment (positive/neutral/negative), primary_issue_category, urgency_level
- Normal fields: customer_intent, mentioned_features, suggested_action
- Language: vi/en
- Translation: false
- Refusal: non-feedback text (e.g., support ticket about generic topic, internal memo)

**Skill output** (system prompt addition):
```
Expected scope: Customer feedback about payment service. Refuse if text is internal memo, marketing material, or unrelated complaint.

Specific validation:
- sentiment: enum {positive, neutral, negative, mixed}
- primary_issue_category: enum {ux, performance, fees, customer_service, technical_bug, security_concern, other}
- urgency_level: enum {low, medium, high, critical}
```

→ Đây là use case Sales/CS team có thể dùng. Skill generic, không gắn KSNB.

## What NOT to do

❌ KHÔNG hardcode domain taxonomy hoặc critical fields trong skill body — caller truyền vào
❌ KHÔNG skip AH-7 (negative few-shot) bất kể user prefer happy path
❌ KHÔNG suggest prompt tự correct/validate ("if id_number is 11 digits, prepend 0"). Validation là responsibility của downstream validator
❌ KHÔNG output prompt > 2000 tokens (input cost + response time)
❌ KHÔNG include personal data thật trong few-shot — dùng anonymized hoặc fictional
❌ KHÔNG mix output language trong cùng JSON (chỗ tiếng Anh chỗ tiếng Việt)
❌ KHÔNG hardcode model name vào prompt (Claude/GPT/Gemini) — prompt nên portable

## Specialization map

Skill này là foundation. Specialized skills sit on top thay vì duplicate:

| Specialized skill | Inherit từ đây | Thêm vào |
|---|---|---|
| `dev-vision-prompt-designer` | 7 patterns AH + workflow + schema | Vision-specific: image quality assessment, doc type taxonomy KSNB (13 loại), bbox grounding (future) |
| `ba-cv-parser` (TBD) | 7 patterns AH + workflow | CV-specific: section detection, education ranking, experience timeline |
| `legal-clause-extractor` (TBD) | 7 patterns AH + workflow | Legal-specific: clause categorization, parties identification, jurisdiction detection |
| `cross-feedback-analyzer` (TBD) | 7 patterns AH + workflow | Feedback-specific: sentiment lexicon, intent taxonomy |

## Maintenance

- Update khi: phát hiện new anti-hallucination pattern qua QA testing across domains
- Update khi: Anthropic/OpenAI release native structured output mode
- Update khi: thêm specialization map entries
- Version bump: minor cho add pattern/example, major cho schema breaking change
- Test prompts: xem `tests/prompts.md`
