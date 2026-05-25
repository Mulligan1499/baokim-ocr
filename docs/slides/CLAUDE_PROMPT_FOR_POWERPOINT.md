# Prompt cho Claude trong PowerPoint

Copy toàn bộ phần dưới đây (từ "## CONTEXT" đến cuối) → paste vào Claude PowerPoint plugin → Claude sẽ tự tạo slides.

---

## CONTEXT

Tôi cần slide thuyết trình cho phần DEV của cuộc thi "Claude Skill" tại Baokim. Tổng thời gian team 30 phút (BA + DEV + TEST mỗi người 10 phút). Phần của tôi là **DEV trong 10-15 phút**, có **live demo browser ở giữa**.

**Đối tượng**: Ban giám khảo cuộc thi — vừa technical vừa business.

**Project**: Baokim OCR API — bóc tách thông tin từ tài liệu (CCCD, hợp đồng, hóa đơn, tờ khai hải quan...) bằng AI Gemini Vision. Đã build xong 10 Claude Skills + Harness 7-stage pipeline. 73% commits là `[ai-major]` (AI sinh >70% code, dev review pass).

**Câu chuyện chính cần kể**: "Skills + Harness = AI có kiểm soát". 2 framework từ Anthropic + IBM/OpenAI 2026.

---

## YÊU CẦU SLIDE

**Số slide**: 12 slides, 16:9 widescreen.

**Style design**:
- Professional, clean (như slide tech conference của Stripe, Vercel)
- Màu chủ đạo: xanh dương đậm `#1e3a8a` cho heading + xám `#475569` cho subtitle
- Font sans-serif, dễ đọc (Inter / Calibri)
- Mỗi slide có header `Baokim OCR API — Cuộc thi Claude Skill 2026` và footer `Trang N/12`
- Tránh wall-of-text — dùng bullet ngắn, bảng, ASCII diagram

**Speaker notes**: Mỗi slide có speaker notes (1-2 paragraph) để tôi nhớ kịch bản nói. Đặt ở phần Notes của slide.

---

## NỘI DUNG TỪNG SLIDE

### Slide 1 — Title

- Tiêu đề lớn: **Baokim OCR API**
- Subtitle: **Phần DEV · Cuộc thi Claude Skill 2026**
- Tagline center: **"Skills + Harness = AI có kiểm soát"**
- Tên người trình bày: **Nguyễn Văn Duy** — Senior PHP Dev, Baokim
- Email: duynv@baokim.vn

**Speaker notes**: "Chào ban giám khảo. Em là Duy, dev senior của team. Hôm nay em chia sẻ phần DEV của dự án OCR — 10-15 phút. Câu chuyện em muốn kể: Skills + Harness = AI có kiểm soát. Sẽ có live demo OCR thật giữa bài."

### Slide 2 — Triết lý thiết kế

- Quote box: *"2025 là năm của Agent. 2026 là năm của Harness."* — Tejas Kumar, IBM AI Engineer Conference
- Câu chốt: **Team không build 1 OCR API. Team build cỗ máy tự cải thiện** dựa trên 2 framework:

Bảng 2 cột:

| Framework | Vai trò trong dự án |
|---|---|
| **Agent Skills** (Anthropic + Supabase) | Số hóa nghiệp vụ thành **10 skill .md** tái sử dụng cross-project |
| **Harness Engineering** (OpenAI + IBM) | **7-stage pipeline** ép AI tuân kỷ luật: validate, mask PII, verify math |

- Cuối slide (size nhỏ): Trích đề bài Section 8: *"Tận dụng AI thông minh **có kiểm soát**, không phải làm bằng tay tất cả"*

**Speaker notes**: "Đề bài Section 8 cảnh báo rõ: 'API đơn giản hoạt động tốt > sản phẩm phức tạp demo lủng củng'. Team chọn approach kép — Skills (đóng gói kiến thức) + Harness (rào chắn AI). 2 framework đều từ Big Tech 2025-2026."

### Slide 3 — Skills layer (10 skills tự xây)

Title: **10 Skills tự xây — Tài sản số của team**

Bảng 3 tier:

| Tier | Skills | Cross-team? | Cross-project? |
|---|---|---|---|
| **Tier 1 (Cross-team)** | cross-llm-extraction-prompt · vn-pii-masker · feedback-to-skill-refiner | ✅ BA+DEV+TEST | ✅ Mọi dự án |
| **Tier 2 (Cross-project Baokim)** | dev-baokim-laravel-repo-pattern · dev-baokim-sql-reviewer · **dev-baokim-api-design** ⭐ NEW · ocr-confidence-aggregator | DEV | ✅ Mọi project Baokim |
| **Tier 3 (OCR-specific)** | dev-vision-prompt-designer · ocr-document-classifier · ocr-extraction-validator | DEV | OCR domain |

Highlight box bên dưới: **7/10 skills cross-reusable** → đây là tài sản dùng được cho dự án tiếp theo, không phải code throwaway.

**Speaker notes**: "10 skills không phải to-do list — mỗi cái là 200-400 dòng markdown encode tacit knowledge. Tier 1 (3 cái) dùng được cho mọi team mọi dự án. Skill `cross-llm-extraction-prompt` là foundation — encode 7 anti-hallucination patterns. Tier 2 Baokim-specific — vd skill `dev-baokim-api-design` em đóng gói AC v1 work tuần này thành standards cho dự án tiếp theo. Đề bài Section 9.4 hỏi 'Skill team khác dùng lại được không?' — câu trả lời: 7/10 yes."

### Slide 4 — Harness 7-stage pipeline

Title: **Harness — 7 stages, mỗi stage là 1 rào chắn**

ASCII pipeline diagram (giữ format monospace):

```
POST /api/v1/ocr/extract  (file)
        ↓
Stage 0: Permission Gate  (Laravel validate, MIME, size, multi-file reject)
        ↓
Stage 0.5: Page Count     (Smalot PDF, reject > 20 trang)
        ↓
Stage 1: Classifier       (Gemini 2.5 Flash → doc_type + language)
        ↓
Stage 2: Vision Extractor (Gemini 2.5 Flash → raw_text + key_values + translation)
        ↓
Stage 3a: Rule Validator  (regex CCCD/MST/phone — deterministic)
Stage 3b: LLM Judge       (independent, no image, anti-bias dương)
Stage 3c: Cross-field ⭐  (math: subtotal+tax=total; dates consistent)
        ↓
Stage 4: PII Masker       (giữ 4 ký tự cuối — audit log, response giữ RAW cho KSNB)
        ↓
Stage 5: Confidence Aggregator (weighted: critical 2× normal 1×)
        ↓
Stage 6: Persist + Audit  (3 bảng MySQL, partition by month)
```

**Speaker notes**: "IBM Tejas Kumar: Harness là mọi thứ bao bọc xung quanh model để neo nó vào reality. Stage 3 đặc biệt — chia 3 sub-stage. 3a regex deterministic. 3b LLM judge độc lập với Stage 2, không xem ảnh, chỉ check text plausibility — anti-bias dương. 3c cross-field check math thuần code. Stage 4 PII masker: response giữ raw cho KSNB copy/paste, audit log mới mask giữ 4 ký tự cuối theo chuẩn."

### Slide 5 — 7 Anti-hallucination patterns

Title: **7 patterns chống AI bịa** (skill `cross-llm-extraction-prompt`)

```
AH-1: Empty value instead of guess (value="", confidence=0)
AH-2: Per-field confidence ∈ [0, 1]
AH-3: Refusal pattern (non-document → document_detected=false)
AH-4: Reasoning trace trước khi extract
AH-5: Critical field validation flag (CCCD 12 digit, không auto-correct)
AH-6: Translation grounding (chỉ dịch text đã extract)
AH-7: Negative few-shot example  ⭐⭐⭐ SINGLE POINT OF FAILURE
```

Highlight box: **Story thực**: Lần đầu prompt thiếu AH-7 → Gemini sinh số CCCD `001234567890` **không có trong ảnh**.

Fix code snippet (small font):
```json
// Negative anchor example trong prompt
{"key": "so_cccd", "value": "", "confidence": 0,
 "flagged_low_confidence": true, "validation_passed": false}
```

→ Sau fix: re-test 10 ảnh bị che → 10/10 trả `value=""` đúng, không bịa.

**Speaker notes**: "AH-7 quan trọng nhất — negative few-shot. Nếu prompt chỉ có positive example, LLM học pattern 'phải trả về một số'. Thêm 1 negative example 'trả về rỗng' → LLM hiểu rỗng là OK. Đây là bài học thực — em sẽ kể chi tiết ở slide AI sai ở đâu sau."

### Slide 6 — "Be opinionated" — Ép workflow

Title: **"Be opinionated" — ép KSNB workflow an toàn**

Subtitle: Nguyên tắc Supabase #3: *"Bạn là chuyên gia, đừng để AI tự chọn cách làm"*

Bảng quyết định:

| Quyết định | Lý do |
|---|---|
| ❌ Reject Word/Excel | Hidden sheet, tracked changes → leak data compliance. Bắt Save As → PDF |
| ❌ Reject PDF > 20 trang | Cap để KSNB tách file → đỡ cost + quality cao |
| ✅ Hash idempotency 24h | KSNB hay re-upload — auto-dedupe, tiết kiệm ~30% cost |
| ✅ Auto-extend cache | Re-upload cùng hash → extend cache thêm 24h |
| ❌ Auto-correct CCCD format sai | Stage 2 KHÔNG sửa. Stage 3a flag để KSNB review. AI không bịa |
| ✅ Reject `file[]` array notation | Chỉ 1 file/lần upload, reject 400 chuẩn |

Cuối slide: Skill `dev-baokim-api-design` (NEW) đóng gói 8 quyết định này thành standards Baokim.

**Speaker notes**: "Khi BA hỏi nhận Word/Excel không, em từ chối vì compliance risk — Excel có hidden sheet, Word có tracked changes — KSNB không thấy nhưng AI vẫn đọc. Save As PDF = WYSIWYG, an toàn hơn. AC đề bài ghi PDF 10 trang, em raise 20 vì khảo sát KSNB nói báo cáo tài chính 15-50 trang — deviation có chủ đích."

### Slide 7 — LIVE DEMO plan

Title: **🎬 LIVE DEMO · 3 documents · 5-7 phút**

Centered list:

1. **CCCD Việt Nam** — happy path, all critical 95%+
2. **TQ ID front** — foreign national_id, Hán-Việt phonetic
3. **CD.pdf** — customs declaration TQ **4 trang**, multipage + Chinese translation

URL big text: `http://127.0.0.1:8000/ocr`

**Speaker notes**: "Chuyển sang browser. Mở /ocr → upload 3 doc theo thứ tự. Doc 1 CCCD VN: highlight key_values table, copy field, confidence badge. Doc 2 TQ ID: highlight doc_type=CMND nước ngoài (skill mới add), Hán-Việt phonetic. Doc 3 CD.pdf: tab Trang 1-4, click Mở tất cả, bản dịch tiếng Việt cả 4 trang. Nếu chậm, skip Doc 1 và bắt đầu từ Doc 2."

### Slide 8 — Demo screenshots (phòng hờ)

Title: **Kết quả demo (phòng hờ nếu live fail)**

Câu warning: **Nếu live demo fail** (network/server), dùng slide này.

3 placeholder cho screenshot (tôi sẽ paste sau):

- 📷 **Doc 1 — CCCD VN**: confidence 96%, doc_type=cccd, 4 critical pass
- 📷 **Doc 2 — TQ ID**: confidence 89%, doc_type=national_id_foreign, name_phonetic_vi="Vương Tiểu Minh"
- 📷 **Doc 3 — CD.pdf**: page_count=4, tab pagination Trang 1-4, translation 2,979 ký tự VN

Cuối slide: Tất cả result lưu DB, truy xuất qua `GET /api/v1/ocr/history/{request_id}`.

**Speaker notes**: "Slide phòng hờ — chỉ show nếu live demo crash. 3 doc đã chạy thật trên server, kết quả lưu DB."

### Slide 9 — "AI sai ở đâu" — 3 stories thực

Title: **"AI sai ở đâu" — 3 stories thực**

Bảng 3 cột:

| Story | AI sai như thế nào | Fix |
|---|---|---|
| **1. LLM bịa CCCD** | Prompt thiếu AH-7 → Gemini sinh số CCCD `001234567890` không có trong ảnh | Thêm negative few-shot vào skill + Stage 3a regex check 12 digit |
| **2. TQ ID classify "other"** | Chưa có doc_type cho ID nước ngoài → fallback "other" → critical fields=[] → confidence sai | Add skill `national_id_foreign` + Stage 1 few-shot Chinese ID |
| **3. JSON truncated** | Tài liệu TQ dày → 7989/**8000 tokens** → JSON cắt giữa → `RuntimeException: No JSON object found` | maxTokens 8000 → 16000 + config-driven per stage |

Log snippet bên dưới (font monospace nhỏ):
```
[2026-05-21] llm.gemini.request_ok {"output_tokens":7989, "finish_reason":"MAX_TOKENS"}
[2026-05-21] OCR pipeline failed {"exception":"RuntimeException","message":"No JSON object found..."}
```

Highlight box: **Loop tự sửa**: gặp lỗi → identify root cause → update **skill** (không hard-code) → ghi `[ai-major]` commit.

**Speaker notes**: "Đề bài Section 7.5 hỏi 'Bài học AI sai ở đâu'. Em kể 3 stories thực. Story 1 ngày đầu Stage 2 prompt chỉ có positive — Gemini sinh CCCD ảo. Em mở ảnh nhìn lại không có số đó. Story 2 hôm trước tester upload TQ ID → classify other → bug R3 formula. Em không sửa code aggregator (đúng rồi) mà thêm SKILL mới. Story 3 customs declaration TQ → MAX_TOKENS → raise lên 16000. Key insight: 3 lỗi đều fix bằng UPDATE SKILL, không phải HARDCODE."

### Slide 10 — Feedback Loop "AI viết AI"

Title: **AI viết AI — qua GitHub PR**

Vertical flow diagram (5 boxes nối bằng mũi tên xuống):

```
KSNB action (copy, edit, skip, mark wrong)
        ↓
ocr_user_actions table (MySQL, implicit feedback)
        ↓
php artisan ocr:analyze-actions --since=7days --pr
        ↓
ActionAnalyzer → feedback-to-skill-refiner SKILL
        ↓
LLM analyze pattern → {observation, hypothesis, proposed_diff}
        ↓
gh CLI tạo branch + commit + PR tự động
        ↓
Engineer REVIEW PR → approve/reject → merge
```

Bullet quan trọng dưới diagram:
- Agent **KHÔNG tạo skill mới** (human-only decision)
- Chỉ refine existing 10 skills theo decision tree A-H
- Human ở trung tâm review

Quote cuối slide: *"Bất cứ thứ gì Claude viết ra đều có thể tái sử dụng bởi version tương lai của chính nó"* — Anthropic [00:13:20]

**Speaker notes**: "Phần em tự hào nhất — killer feature. KSNB làm gì? Copy, sửa, skip, đánh dấu sai. Không cần form feedback — implicit từ workflow. Mỗi action lưu DB. Sau 1 tuần dev chạy artisan command. LLM đọc patterns: 'field X bị edit 10/12 lần' → đề xuất diff. Agent tạo PR qua gh CLI. Engineer review UI quen thuộc. Quan trọng: AGENT KHÔNG TẠO SKILL MỚI — đó là strategic decision của human. Đây là Garbage Collection Day của OpenAI + Continuous Learning của Anthropic kết hợp."

### Slide 11 — Số liệu git audit

Title: **Số liệu thực — Git audit + Tests**

3 stat boxes lớn ở giữa slide:

- **15** Commits
- **11 / 15** = **73%** prefix `[ai-major]` (AI sinh >70% code)
- **35 / 35** Tests ✅ pass

Bảng phụ:

| Metric | Value |
|---|---|
| Skills cross-reusable | **7/10** |
| Doc types support | **16** (cccd, national_id_foreign, passport, gpkd, contract VI/EN/ZH, invoice, customs_declaration, bill_of_lading, aml_charter, power_of_attorney, labor_contract, financial_report, ...) |
| Field labels VN | **222** |
| Critical PHP files | ~30 (services + repos + resources) |

Code block:
```bash
$ git log --oneline | grep -c "\[ai-major\]"   # 11
$ git log --oneline | grep -c "\[ai-assist\]"  # 1
$ git log --oneline | grep -c "\[human\]"      # 2
```

Cuối slide: Đề bài Section 9.2: *"Đoạn nào AI sinh ra? Team review thế nào?"* — answer: **73% AI-major, review qua PR convention**.

**Speaker notes**: "Số liệu git log thật. 73% commit là AI sinh ra >70% code đã review pass. 13% human-only — BKM rules manual edit, secrets management. 7% ai-assist — dev edit nhiều. 35 tests pass cover Stage 4 PII masker, Stage 3 rule validator, OcrPipelineIntegration với fake LLM, AnalyzeActions với mock LLM. Em dùng commit prefix convention để track AI contribution rõ ràng — answer trực tiếp cho câu hỏi đề bài."

### Slide 12 — Take-aways (closing)

Title: **Take-aways**

4 take-aways với emoji icon (mỗi cái 1 dòng + 1 sub-line giải thích):

🎯 **Skills là tài sản số của team**
Lưu git, tái sử dụng cross-project. 7/10 skill dùng được cho dự án Baokim tiếp theo.

⚙️ **Harness ép AI tuân kỷ luật**
Stage 3a/3b/3c independent · Stage 4 PII mask · Verify math (subtotal+tax=total).

🔄 **AI sai → tự cải thiện qua PR review**
Implicit feedback → LLM analyze → GitHub PR → human approve. Loop khép kín.

💡 **Tận dụng AI thông minh có kiểm soát**
Section 8 đề bài: "API đơn giản, hoạt động tốt > sản phẩm phức tạp lủng củng" ✅

Cuối slide center: **Cám ơn ban giám khảo.**
Q&A: duynv@baokim.vn

**Speaker notes**: "4 take-aways. Đề bài Section 8 nhấn mạnh đơn giản hoạt động tốt > phức tạp lủng củng — em check box này. Skills là tài sản số (Anthropic). Harness rào chắn (IBM). Loop tự cải thiện (Section 7.5 + 9.4 đề bài). Sẵn sàng Q&A."

---

## OUTPUT EXPECTATION

Tạo PowerPoint file (.pptx) **editable**, với:
- 12 slides theo cấu trúc trên
- Mỗi slide có speaker notes ở phần Notes
- Theme professional clean (xanh đậm + xám + trắng)
- Header/footer mỗi slide
- Bảng và code block render rõ
- ASCII diagram giữ monospace font
- Layout 16:9

Sau khi tạo, tôi sẽ tự edit thêm: thay screenshot vào slide 8, chỉnh wording 1 số chỗ.
