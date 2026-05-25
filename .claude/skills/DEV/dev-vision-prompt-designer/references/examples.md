# Examples — Vision Prompt Designer

Reference file lazy-load. 4 anchor examples để adapt prompt cho doc type cụ thể.
Cho overview workflow + 7 anti-hallucination patterns → xem [../SKILL.md](../SKILL.md).
Cho templates skeleton → xem [templates.md](templates.md).

## Example 1: CCCD tiếng Việt (happy path)

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

## Example 2: Vận đơn tiếng Trung (translation + critical fields)

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

## Example 3: Ảnh mờ → anti-hallucination kick in (regression test case)

**Input to skill**: doc_type=id_card, ảnh CCCD bị che vùng id_number

**Skill behavior expected**:
- Prompt generated phải có Example 1 negative (ở trên) làm few-shot
- Khi runtime gặp ảnh tương tự → model trained-by-context biết trả `value=""`
- Nếu skill output prompt thiếu negative example → output warning rõ ràng cho dev

→ Đây là regression test case bắt buộc trong `tests/prompts.md`.

## Example 4: Reusability — adapt cho CV (HR domain, ngoài 15 loại KSNB)

**Input to skill**:
- doc_type: cv (mới, không có trong 15 loại enum)
- critical_fields: candidate_name, email, phone, current_company, years_experience
- normal_fields: education, skills, languages
- language: vi
- translate: false

**Skill output**: vẫn hoạt động! Skill này KHÔNG hardcode 15 doc types KSNB. Workflow + 7 patterns AH chạy cho bất kỳ doc type nào dev truyền vào.

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

→ Đây là reusability angle. Skill `dev-vision-prompt-designer` hoạt động cho bất kỳ doc type nào dev truyền vào, không chỉ 15 loại KSNB.
