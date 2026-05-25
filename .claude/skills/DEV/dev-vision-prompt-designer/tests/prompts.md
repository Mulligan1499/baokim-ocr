# Test prompts: dev-vision-prompt-designer

Test set để verify skill trigger và output đúng. Run periodically (≥30 phút/2 tuần theo file 03 primer).

## Trigger tests (skill SHOULD activate)

Skill phải auto-load khi user gõ các prompt sau:

1. "Viết prompt vision cho CCCD"
2. "Design prompt OCR cho hợp đồng tiếng Anh"
3. "Tôi cần prompt extract key-value từ ảnh hóa đơn"
4. "Anti-hallucination prompt cho vận đơn tiếng Trung"
5. "Structured output schema cho extraction tài liệu pháp lý"
6. "Prompt template cho Stage 2 Vision Extractor"
7. "Thiết kế prompt cho passport scan"
8. "Claude Vision prompt cho tờ khai hải quan"

**Pass criteria**: Claude load skill, output theo workflow 4 steps, generate 4 components (system prompt + user template + schema + ≥2 examples).

## Anti-trigger tests (skill should NOT activate)

Các prompt sau phải KHÔNG kích hoạt skill này:

1. "Viết prompt chatbot cho customer support" — text-only, không vision
2. "Setup Tesseract OCR Python cho dự án" — không phải LLM
3. "Prompt generate logo bằng DALL-E" — image generation, không extraction
4. "Review giúp prompt vision tôi viết, có lỗi không" — debug/review, dùng prompt-reviewer skill
5. "Skill này có handle anti-hallucination không" — hỏi meta về skill, không phải design

**Pass criteria**: Claude trả lời normal không reference skill này.

## Output quality tests

### OQ-1: Happy path basic
**Input**: "Prompt cho CCCD, critical fields: id_number, full_name, date_of_birth"

**Expected output**:
- System prompt có specialized rules cho CCCD (12 digits, date format)
- User prompt template có {variables}
- JSON output schema đầy đủ
- 1 positive example (CCCD rõ nét → đầy đủ fields high confidence)
- 1 negative example (CCCD mờ → id_number="", confidence=0)
- Anti-hallucination checklist 7 items đã cover hết

### OQ-2: Auto detect mode
**Input**: "Prompt cho document_type=auto, language=auto, không biết trước loại tài liệu"

**Expected output**:
- Prompt có detection step trước extraction (AH-4 reasoning trace mạnh hơn)
- Schema có field `document_type_actual` để model fill
- Fallback handling khi không match taxonomy
- Warning cho dev: "Generic prompt accuracy thấp hơn specialized — recommend Stage 1 Classifier trước"

### OQ-3: Reusability test (cross-domain)
**Input**: "Prompt cho CV ứng viên HR, critical: candidate_name, email, phone, current_company. Đây là domain HR không phải KSNB"

**Expected output**:
- Skill vẫn generate prompt OK (KHÔNG refuse vì doc type ngoài 15 loại KSNB)
- Critical fields validation specialized cho CV (email regex, phone VN format)
- Note ở cuối output: "Skill này domain-agnostic. Tuần 3 sẽ tách taxonomy ra references/ để clean hơn"

### OQ-4: Anti-trigger redirect
**Input**: "Đây là prompt tôi viết: [paste prompt]. Skill check giúp anti-hallucination chưa?"

**Expected output**:
- Skill REFUSE (output ngắn, không generate gì)
- Redirect: "Đây là review task, dùng `prompt-reviewer` skill (chưa build). Tạm thời tôi có thể đánh giá manual nếu anh muốn"
- KHÔNG generate new prompt từ đầu

### OQ-5: Translation rules verification
**Input**: "Prompt cho hợp đồng tiếng Trung, dịch sang VN"

**Expected output**:
- Pattern AH-6 (Translation grounding) explicit trong system prompt
- Distinction rõ giữa text fields (dịch) vs codes/numbers (không dịch)
- Cho tiếng Trung: rule "name = original Chinese, value_translated_vi = Hán-Việt phonetic"
- Example minh hoạ rule trên

### OQ-6: Negative few-shot enforcement
**Input**: "Prompt cho passport, focus vào happy path là chính, edge case ít gặp"

**Expected output**:
- Skill IGNORE phần "focus happy path" của user
- Vẫn enforce ≥1 negative few-shot example trong output
- Explain lý do: "AH-7 là core, không skip ngay cả khi user prefer happy path"

## Regression tests (phát hiện skill bị degrade)

### RG-1: Pattern coverage check
Run: "Generate prompt cho id_card và list ra 7 patterns AH trong output"

**Pass criteria**: Output liệt kê đủ AH-1 đến AH-7 trong prompt, không skip pattern nào.

### RG-2: Negative example presence
Run: "Prompt cho invoice, language=vi" → đếm số few-shot examples trong output

**Pass criteria**: ≥2 examples, trong đó ≥1 negative (value="" hoặc document_detected=false).

### RG-3: Cross-stage consistency
Run: Generate prompt cho id_card, sau đó hỏi "critical fields có match với Stage 5 Confidence Aggregator weight 2 không?"

**Pass criteria**: Skill confirm match (id_number, full_name, date_of_birth, date_of_issue — đúng theo Section 10 file 02-tech-decisions).

## Test execution log

| Date | Tester | Pass rate | Notes |
|---|---|---|---|
| 2026-05-15 | (initial) | TBD | Skill mới build, chưa test |

Update sau mỗi lần test run.
