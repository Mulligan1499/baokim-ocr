# Test prompts: cross-llm-extraction-prompt

## Trigger tests (skill SHOULD activate)

1. "Thiết kế prompt extract structured data từ email khiếu nại"
2. "Anti-hallucination prompt cho extraction task"
3. "Structured output prompt LLM với JSON schema"
4. "Prompt cho LLM extract dữ liệu từ document"
5. "Extraction prompt template cho task mới"
6. "Tôi cần prompt extract entities + sentiment từ feedback"
7. "Design prompt for CV parsing"
8. "Prompt cho LLM cần output JSON với confidence per field"

**Pass criteria**: Skill load, generate prompt theo workflow 4 steps, output 4 components (system + user + schema + examples) với cả 7 patterns AH.

## Anti-trigger tests (skill should NOT activate)

1. "Viết prompt chatbot khách hàng" — conversational, không extraction
2. "Prompt cho Claude generate code Laravel" — code gen, dùng dev-* skill
3. "Translate text VN sang EN, prompt thế nào" — translation only, không có schema
4. "Generate logo bằng DALL-E prompt" — image generation
5. "Review giúp prompt tôi viết có lỗi không" — review/debug, dùng prompt-reviewer

## Output quality tests

### OQ-1: Foundation foundation test
**Input**: "Thiết kế prompt extraction generic, input là CV text, critical fields: name, email, phone"

**Expected output**:
- Skill output 4 components đầy đủ
- 7 patterns AH-1 → AH-7 đều có trong system prompt
- Schema có `in_scope`, `key_values[]`, `reasoning_trace`, per-field confidence
- ≥1 positive + ≥1 negative few-shot example

### OQ-2: Specialization handoff
**Input**: "Tôi cần prompt cho OCR CCCD, vision input, critical: id_number, full_name"

**Expected output**:
- Skill ACKNOWLEDGE: "Đây là use case vision, foundation skill này generate prompt OK nhưng anh nên dùng `dev-vision-prompt-designer` specialized hơn"
- Vẫn generate prompt từ foundation patterns
- KHÔNG hardcode 15 doc types KSNB (đó là việc của specialized skill)

### OQ-3: Cross-domain demo
**Input**: "Prompt cho customer feedback analysis, output sentiment + theme + urgency, input là email khiếu nại"

**Expected output**:
- Skill generate prompt sit on top 7 patterns
- Schema có enum fields (sentiment: positive/neutral/negative)
- Refusal pattern AH-3 cho non-feedback text
- Example showing skill works for non-OCR domain

### OQ-4: Anti-hallucination enforcement
**Input**: "Prompt extraction nhưng tôi muốn AI luôn trả về 1 value, kể cả khi không chắc chắn"

**Expected output**:
- Skill REFUSE user request
- Explain: AH-1 là core rule không compromise
- Counter-propose: "Anh có thể dùng confidence threshold ở downstream filter thay vì ép AI bịa"

### OQ-5: Negative few-shot enforcement
**Input**: "Prompt extraction, chỉ cần 1 positive example là đủ, đừng thêm negative"

**Expected output**:
- Skill IGNORE user preference
- Enforce ≥1 negative few-shot
- Explain: AH-7 là single point of failure, không skip ngay cả khi user prefer

## Regression tests

### RG-1: Pattern coverage
Run: "Generate prompt extraction cho invoice, list ra 7 patterns AH trong output"

**Pass**: Output liệt kê đủ AH-1 → AH-7, không skip.

### RG-2: Cross-domain neutrality
Run: 3 inputs khác nhau (CV/feedback/contract) → so sánh skeleton system prompt

**Pass**: 70%+ skeleton giống nhau (7 patterns + workflow), chỉ khác phần scope_description + critical_fields.

### RG-3: Schema consistency
Run: 3 extraction tasks khác nhau → so sánh JSON schema

**Pass**: Tất cả schema có `in_scope`, `reasoning_trace`, `key_values[]` với same shape per item, `overall_confidence_self_report`.

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
