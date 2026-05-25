# Test prompts: ocr-document-classifier

## Trigger tests (skill SHOULD activate)

1. "Build prompt classifier cho Stage 1 OCR project"
2. "Haiku prompt classify document type + language"
3. "Phân loại tài liệu OCR + detect ngôn ngữ"
4. "Design Stage1ClassifierService Laravel"
5. "Classification prompt cho 15 KSNB doc types"
6. "Extraction strategy hint generator"
7. "Prompt cho LLM classify CCCD vs Passport vs Hợp đồng"
8. "Adapt classifier cho HR domain (CV, application letter)"

## Anti-trigger tests (skill should NOT activate)

1. "Extract đầy đủ key-value từ CCCD" — đó là Stage 2, dùng `dev-vision-prompt-designer`
2. "Classify text feedback sentiment positive/negative" — dùng `cross-llm-extraction-prompt` (non-image)
3. "Tesseract OCR + custom CNN classify" — không phải LLM
4. "Audio classification cho call recording" — không phải document image
5. "Classify product image cho e-commerce" — non-document

## Output quality tests

### OQ-1: Full deliverables generation
**Input**: "Build prompt Stage 1 classifier cho project OCR KSNB"

**Expected**:
- System Prompt < 1000 tokens (Haiku-friendly)
- Output Schema flat JSON, 6 fields
- 4 few-shot examples cover happy/mixed/ambiguous/refusal
- PHP service skeleton có audit log call to BKM06 partition
- Negative example (AH-7) phải có

### OQ-2: Conservative bias enforcement
**Input**: "Prompt cho classifier, tôi muốn AI mạnh dạn chọn class cụ thể thay vì trả `other`"

**Expected**:
- Skill REFUSE user preference
- Explain: conservative bias là rule (avoid wrong class), `other` là valid output
- Counter-propose: nếu accuracy low → optimize prompt, không phải remove `other`

### OQ-3: Taxonomy adaptation
**Input**: "Adapt classifier cho HR: cv, application_letter, employment_contract"

**Expected**:
- System prompt regenerate với HR taxonomy
- KHÔNG còn reference KSNB 15 doc types
- Examples adapt: CV happy path, application_letter refusal case
- Note: cùng skill, đổi taxonomy

### OQ-4: PHP service compliance
**Input**: "Code Stage1ClassifierService.php"

**Expected**:
- Constructor inject AnthropicClient + OcrVendorCallRepository (BKM03)
- KHÔNG dùng DB::table (BKM02)
- Audit log mỗi call vào ocr_vendor_calls (BKM06)
- Type hints + return types đầy đủ
- KHÔNG có FOREIGN KEY trong migration reference (BKM01)

### OQ-5: Cost awareness
Run: "Skill suggest model nào cho Stage 1?"

**Expected**: Haiku 4.5 explicitly (not Sonnet 4.6). Rationale: classifier task simple, Haiku đủ + 6x rẻ hơn.

## Regression tests

### RG-1: Haiku optimization
Run: count tokens trong System Prompt output

**Pass**: < 1000 tokens (Haiku context efficient).

### RG-2: Negative few-shot presence
Run: 3 different domain inputs → check few-shot examples

**Pass**: 3/3 outputs có ≥1 example với `document_detected=false` hoặc `document_type="other"`.

### RG-3: Stage handoff consistency
Run: Stage 1 output schema → check field names match input expected của `dev-vision-prompt-designer` Stage 2

**Pass**: Field names align: `document_type`, `language_detected`, `extraction_strategy_hint` đều mapped.

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build |
