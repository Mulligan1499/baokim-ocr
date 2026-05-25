# Templates — Vision Prompt Designer

Reference file lazy-load. Đọc khi cần copy/adapt prompt cụ thể. Cho overview workflow + 7 anti-hallucination patterns → xem [../SKILL.md](../SKILL.md).

## System Prompt template

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

## User Prompt template

```
[Image attached]

Please analyze the attached document image:
1. Confirm document type and image quality (1-2 sentences in image_quality_note)
2. Extract all fields matching the JSON schema
3. Provide per-field confidence per the calibration scale
{if translate=true: 4. Translate text fields to Vietnamese in value_translated_vi}

Return ONLY valid JSON matching the schema. No additional commentary outside JSON.
```

## Output Schema (JSON)

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
