---
marp: true
theme: gaia
paginate: true
size: 16:9
header: "Baokim OCR API — Cuộc thi Claude Skill 2026"
footer: "DEV part · 10-15 phút"
style: |
  section {
    font-size: 22px;
    padding: 40px 60px;
  }
  section.lead h1 { color: #1e3a8a; }
  section.lead h2 { color: #475569; font-weight: normal; }
  code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
  pre { font-size: 16px; }
  table { font-size: 0.85em; }
  th { background: #1e3a8a; color: white; }
  .tier1 { color: #16a34a; font-weight: bold; }
  .tier2 { color: #ca8a04; }
  .tier3 { color: #64748b; }
  .highlight { background: #fef3c7; padding: 4px 8px; border-radius: 4px; }
  .stat-big { font-size: 2em; color: #1e3a8a; font-weight: bold; }
---

<!-- _class: lead -->

# Baokim OCR API

## Phần DEV · Cuộc thi Claude Skill 2026

**Skills + Harness = AI có kiểm soát**

Trình bày: **Nguyễn Văn Duy** — Senior PHP Dev, Baokim
Email: duynv@baokim.vn

<!--
Speaker notes (~30s):
- Chào ban giám khảo. Em là Duy, dev senior của team.
- Hôm nay em chia sẻ phần DEV của dự án OCR — 10-15 phút.
- Câu chuyện em muốn kể: "Skills + Harness = AI có kiểm soát".
- Sẽ có live demo OCR thật giữa bài.
-->

---

# Triết lý thiết kế

> *"2025 là năm của Agent. 2026 là năm của Harness."*
> — Tejas Kumar, IBM AI Engineer Conference

**Team không build 1 OCR API. Team build cỗ máy tự cải thiện** dựa trên **2 framework**:

| Framework | Của | Vai trò trong dự án |
|---|---|---|
| **Agent Skills** | Anthropic + Supabase | Số hóa nghiệp vụ KSNB thành **10 skill .md** tái sử dụng cross-project |
| **Harness Engineering** | OpenAI + IBM | **7-stage pipeline** ép AI tuân kỷ luật: validate, mask PII, verify math |

→ Đề bài Section 8: *"Tận dụng AI thông minh **có kiểm soát**, không phải làm bằng tay tất cả"*

<!--
Speaker notes (~1 phút):
- Đề bài cuộc thi Section 8 cảnh báo rõ: "API đơn giản, hoạt động tốt > sản phẩm phức tạp demo lủng củng".
- Team chọn approach kép: Skills (đóng gói kiến thức KSNB) + Harness (rào chắn AI).
- 2 framework đều là từ Big Tech 2025-2026, video trên AI Engineer Conference.
- Skills cho phép AI biết "phải làm gì trong nghiệp vụ KSNB". Harness ép AI "không được bịa, không được leak PII".
-->

---

# Skills layer — 10 skills tự xây

Tách 3 tier theo độ tái sử dụng:

| Tier | Skill | Use cross-team? | Use cross-project? |
|---|---|---|---|
| **1** | <span class="tier1">cross-llm-extraction-prompt</span> | ✅ BA+DEV+TEST | ✅ Mọi extraction task |
| **1** | <span class="tier1">vn-pii-masker</span> | ✅ DEV+SEC+TEST | ✅ Mọi log/audit có PII |
| **1** | <span class="tier1">feedback-to-skill-refiner</span> | ✅ Meta-skill | ✅ Mọi loop AI viết AI |
| **2** | <span class="tier2">dev-baokim-laravel-repo-pattern</span> | DEV | ✅ Mọi project Laravel Baokim |
| **2** | <span class="tier2">dev-baokim-sql-reviewer</span> | DEV+DBA | ✅ Mọi migration/query Baokim |
| **2** | <span class="tier2">dev-baokim-api-design</span> ⭐ | DEV | ✅ Mọi REST API Baokim (NEW — encode AC v1 work) |
| **2** | <span class="tier2">ocr-confidence-aggregator</span> | DEV | ✅ Multi-signal scoring (fraud, ranking) |
| **3** | <span class="tier3">dev-vision-prompt-designer</span> | DEV | OCR vision tasks |
| **3** | <span class="tier3">ocr-document-classifier</span> | DEV | LLM-based classifier |
| **3** | <span class="tier3">ocr-extraction-validator</span> | DEV | LLM extraction pipeline |

**7/10 skill cross-reusable** → tài sản số của team, không chỉ phục vụ 1 dự án.

<!--
Speaker notes (~1 phút):
- 10 skills, KHÔNG phải "to-do list" — mỗi skill là 200-400 dòng markdown encode tacit knowledge thật.
- Tier 1 (xanh): dùng được cho mọi team, mọi dự án. Skill `cross-llm-extraction-prompt` là foundation — 7 anti-hallucination patterns.
- Tier 2 (cam): Baokim-specific. Skill `dev-baokim-api-design` là MỚI — em đóng gói AC v1 work tuần này (versioning, error schema R8, idempotency) thành skill cho dự án Baokim tiếp theo dùng lại.
- Tier 3 (xám): scope hẹp hơn — OCR-specific.
- Đề bài Section 9.4 hỏi: "Skill team khác dùng lại được không?". Trả lời: 7/10 yes.
-->

---

# Harness layer — 7-stage pipeline

```
┌──────────────────────────────────────────────────────────┐
│  POST /api/v1/ocr/extract  (file)                        │
└────────┬─────────────────────────────────────────────────┘
         ▼
┌────────────────────┐  Stage 0: Permission Gate (Laravel validate)
│ ExtractRequest     │  → file size, MIME, multi-file reject
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 0.5: Page count enforcement (Smalot PDF)
│ PdfPageCounter     │  → reject PDF > 20 pages
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 1: Classifier (Gemini 2.5 Flash)
│ Stage1Classifier   │  → doc_type + language + strategy hint
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 2: Vision Extractor (Gemini 2.5 Flash)
│ Stage2Vision       │  → raw_text + key_values + translation_vi
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 3a: Rule validator (regex deterministic)
│ Stage3RuleValidator│  → CCCD 12 digit, MST 10 digit, phone, email
├────────────────────┤  Stage 3b: LLM judge (independent, no image)
│ Stage3Validator    │  → anti-bias, plausibility check
├────────────────────┤  Stage 3c: Cross-field math/date logic ⭐
│ Stage3CrossField   │  → invoice subtotal+tax=total, dates consistent
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 4: PII Masker (regex giữ 4 ký tự cuối)
│ Stage4PiiMasker    │  → audit log mask, response giữ RAW cho KSNB
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 5: Confidence Aggregator
│ Stage5Aggregator   │  → weighted critical 2× normal 1× → overall
└────────┬───────────┘
         ▼
┌────────────────────┐  Stage 6: Persist + Audit (3 bảng MySQL)
│ Extraction + Audit │  → ocr_extractions + ocr_audit_logs partition
└────────────────────┘
```

<!--
Speaker notes (~1 phút):
- Đây là Harness mà IBM Tejas Kumar nói. Mỗi stage là 1 "rào chắn" độc lập.
- Stage 3 đặc biệt: chia 3 sub-stage 3a/3b/3c.
  - 3a regex deterministic — code thuần, không LLM.
  - 3b LLM judge — **độc lập với Stage 2**, không xem ảnh, chỉ check text plausibility. Anti-bias dương.
  - 3c cross-field — code thuần, check math (subtotal + tax = total), check date logic. IBM gọi đây là "Verify Step".
- Stage 4 PII masker: RAW vẫn trả KSNB (họ cần copy/paste), audit log thì mask. R6 chuẩn AC: giữ 4 ký tự cuối.
- Stage 6 audit_logs partition by month — TT 39/2016 NHNN yêu cầu retention 5 năm.
-->

---

# Anti-hallucination — 7 patterns trong `cross-llm-extraction-prompt`

```markdown
1. AH-1: Empty value instead of guess — value="", confidence=0
2. AH-2: Per-field confidence calibration ∈ [0,1]
3. AH-3: Refusal pattern — non-document → document_detected=false
4. AH-4: Reasoning trace before extraction
5. AH-5: Critical field validation flag (CCCD 12 digit, không auto-correct)
6. AH-6: Translation grounding — chỉ dịch text đã extract
7. AH-7: Negative few-shot example  ⭐⭐⭐ SINGLE POINT OF FAILURE
```

**Story thực**: lần đầu prompt thiếu AH-7 → Gemini sinh số CCCD `001234567890` không có trong ảnh.

**Fix**: thêm negative example "ảnh có CCCD bị che 4 chữ giữa → emit `value=""` thay vì đoán" vào skill.

```json
// Negative anchor example trong prompt
{"key": "so_cccd", "value": "", "confidence": 0,
 "flagged_low_confidence": true, "validation_passed": false}
```

→ Sau fix: re-test 10 ảnh bị che → 10/10 trả `value=""` đúng, không bịa.

<!--
Speaker notes (~45s):
- Skill `cross-llm-extraction-prompt` là foundation. 7 patterns được battle-tested.
- AH-7 là quan trọng nhất — "negative few-shot". Nếu prompt chỉ có positive example, LLM học pattern "phải trả về một số".
- Có 1 ví dụ negative "trả về rỗng" trong prompt → LLM hiểu "rỗng là OK".
- Đây là bài học thực — em sẽ kể lại ở slide "AI sai ở đâu" sau.
-->

---

# "Be opinionated" — ép workflow, không chiều convenience

Nguyên tắc Supabase #3: *"Bạn là chuyên gia, đừng để AI tự chọn cách làm"*. Team áp dụng:

| Quyết định | Lý do |
|---|---|
| ❌ Reject Word/Excel | Hidden sheet, tracked changes → leak data compliance. Bắt KSNB Save As → PDF |
| ❌ Reject PDF > 20 trang | Báo cáo tài chính dài thật, nhưng cap để KSNB tách file → đỡ cost + quality |
| ✅ Hash idempotency 24h | KSNB hay re-upload cùng file — auto-dedupe, tiết kiệm cost ~30% |
| ✅ Auto-extend cache khi re-upload | Nếu cùng hash, status=done → return doc cũ, extend cache thêm 24h |
| ❌ Auto-correct CCCD format sai | Stage 2 KHÔNG sửa. Stage 3a flag để KSNB review. AI không bịa số |
| ✅ Force reject duplicate field `file[]` | HTTP/PHP allow nhưng KSNB không nên gửi 2 file/lần — reject 400 chuẩn |

→ Skill `dev-baokim-api-design` (NEW) đóng gói 8 quyết định này thành standards Baokim.

<!--
Speaker notes (~1 phút):
- Khi BA hỏi "có nhận Word/Excel không?", em từ chối vì compliance risk. Excel có hidden sheet, Word có tracked changes — KSNB không nhìn thấy nhưng AI vẫn đọc. Bắt Save As PDF → "what you see is what you get".
- AC ghi PDF ≤ 10 trang, team raise lên 20 vì khảo sát KSNB cho thấy báo cáo tài chính 15-50 trang. Deviation có chủ đích.
- Idempotency: KSNB hay vô tình re-upload cùng file → mất phí LLM. Hash sha256 → return cached.
- AI không auto-correct CCCD sai format — Stage 3a chỉ flag. Tránh "AI tự cho mình đúng".
-->

---

<!-- _class: lead -->

# 🎬 LIVE DEMO

## 3 documents · 5-7 phút

1. **CCCD Việt Nam** — happy path, all critical 95%+
2. **TQ ID front** — foreign national_id, Hán-Việt phonetic
3. **CD.pdf** — customs declaration TQ **4 trang**, multipage + translation

URL: `http://127.0.0.1:8000/ocr`

<!--
Speaker notes (~30s):
- Chuyển sang browser. Mở /ocr → upload 3 doc theo thứ tự.
- Doc 1 CCCD VN: highlight key_values table, copy "so_cccd", confidence badge.
- Doc 2 TQ ID: highlight doc_type = "CMND nước ngoài" (skill mới add), value_translated_vi có Hán-Việt phonetic.
- Doc 3 CD.pdf: tab "Trang 1/2/3/4", click "Mở tất cả", bản dịch tiếng Việt cả 4 trang.
- Time check: nếu chậm, skip Doc 1 và bắt đầu từ Doc 2.
-->

---

# Demo screenshots (phòng hờ live fail)

> **Nếu live demo fail** (network/server crash), dùng slide này thay thế.

📷 **Doc 1 — CCCD VN**: confidence 96%, doc_type=cccd, 4 critical pass
📷 **Doc 2 — TQ ID**: confidence 89%, doc_type=national_id_foreign, name_phonetic_vi="Vương Tiểu Minh"
📷 **Doc 3 — CD.pdf**: page_count=4, tab pagination "Trang 1-4", translation 2,979 ký tự VN

```bash
# Trong trường hợp live fail, screenshot tại:
ocr-api/docs/slides/screenshots/{1_cccd,2_tq_id,3_cd_pdf}.png
```

→ Tất cả result lưu DB, truy xuất qua `GET /api/v1/ocr/history/{request_id}` — AC-05 chuẩn.

<!--
Speaker notes (~30s):
- Đây là slide phòng hờ — sẽ chỉ show nếu live demo crash.
- 3 doc đã chạy thật trên server, kết quả lưu DB, có thể truy xuất bất cứ lúc nào qua API.
-->

---

# "AI sai ở đâu" — 3 stories thực

| Story | AI sai như thế nào | Fix |
|---|---|---|
| **1. LLM bịa CCCD** | Lần đầu prompt thiếu AH-7 → Gemini sinh số CCCD `001234567890` không có trong ảnh | Thêm negative few-shot vào `dev-vision-prompt-designer` + Stage 3a regex check 12 digit |
| **2. TQ ID classify "other"** | Skill chưa có doc_type cho ID nước ngoài → classifier fallback "other" → critical fields=[] → confidence sai cách tính | Add skill `national_id_foreign` vào taxonomy + Stage 1 few-shot Chinese ID |
| **3. JSON truncated** | Tài liệu TQ dày → output 7989/**8000 tokens** → `finish_reason: MAX_TOKENS` → JSON cắt giữa → crash `RuntimeException: No JSON object found` | maxTokens 8000 → 16000 + move ra `config/ocr.php` để env override |

```log
[2026-05-21 20:34:46] llm.gemini.request_ok {"output_tokens":7989, "finish_reason":"MAX_TOKENS"}
[2026-05-21 20:34:46] OCR pipeline failed {"exception":"RuntimeException","message":"No JSON object found..."}
```

→ **Loop tự sửa**: gặp lỗi → identify root cause → update **skill** (không phải hard-code) → ghi vào `[ai-major]` commit.

<!--
Speaker notes (~1.5 phút):
- Đề bài Section 7.5 hỏi: "Bài học: AI sai ở đâu, team xử lý ra sao?". Em kể 3 stories thực.
- Story 1: ngày đầu Stage 2 prompt chỉ có positive example. Test với ảnh CCCD mờ → Gemini sinh số "001234567890". Em sốc, mở ảnh nhìn lại không có số đó. Đây chính là AH-7 — negative example là single point of failure.
- Story 2: Hôm trước tester upload TQ ID, classify "other" → bug R3 formula. Em không phải sửa code aggregator (đúng rồi), mà thêm SKILL mới `national_id_foreign`. Knowledge approach thay vì code patch.
- Story 3: Customs declaration TQ — output 7989/8000 tokens. Em raise lên 16000 + move config-driven.
- Key insight: 3 lỗi đều fix bằng UPDATE SKILL, không phải HARDCODE. Đây là Skill-driven dev.
-->

---

# Feedback Loop — "AI viết AI" qua GitHub PR

```
   ┌─────────────────┐
   │  KSNB action    │  (copy, edit, skip, mark wrong)
   └────────┬────────┘
            ▼
   ┌─────────────────┐
   │ ocr_user_actions│  (MySQL table — implicit feedback)
   └────────┬────────┘
            ▼
   ┌─────────────────┐  php artisan ocr:analyze-actions --since=7days --pr
   │ ActionAnalyzer  │
   └────────┬────────┘
            ▼
   ┌─────────────────┐  Gemini LLM phân tích pattern theo decision tree A-H
   │ feedback-to-    │  → output {pattern_id, observation, proposed_diff}
   │ skill-refiner   │
   └────────┬────────┘
            ▼
   ┌─────────────────┐  gh CLI tạo branch + commit + PR
   │ GitHub PR       │  Title: "[Skill update] N patterns from KSNB feedback"
   └────────┬────────┘
            ▼
   ┌─────────────────┐  Human REVIEW → approve/reject → merge
   │ Engineer        │  Agent KHÔNG tạo skill mới (human-only decision)
   └─────────────────┘
```

→ Anthropic [00:13:20]: *"Bất cứ thứ gì Claude viết ra đều có thể tái sử dụng bởi version tương lai của chính nó"*

<!--
Speaker notes (~1.5 phút):
- Đây là phần em tự hào nhất — KILLER FEATURE.
- KSNB làm gì? Họ copy, sửa, skip, đánh dấu sai. Không cần fill form feedback — implicit từ workflow.
- Mỗi action lưu vào `ocr_user_actions` table. Sau 1 tuần, dev chạy artisan command.
- LLM đọc patterns: "field X bị edit 10/12 lần trong doc_type Y" → đề xuất diff cho skill liên quan.
- Agent tạo branch + commit + PR qua `gh` CLI. Title: "[Skill update]...".
- Engineer review PR trên GitHub UI quen thuộc. Approve → merge → skill updated.
- Quan trọng: AGENT KHÔNG TẠO SKILL MỚI. Skill mới là strategic decision của human. Agent chỉ refine existing 10 skills.
- Đây là "Garbage Collection Day" của OpenAI + "Continuous Learning" của Anthropic kết hợp.
-->

---

# Số liệu thực — git audit + tests

<div class="stat-big">15</div>

Commits tổng, **11 [ai-major]** (73%) · 1 [ai-assist] · 2 [human] · 1 misc

<div class="stat-big">10 skills · 7 stages · 35 tests ✅</div>

| Metric | Value |
|---|---|
| Skills cross-reusable | **7/10** (Tier 1 + Tier 2) |
| Test pass | **35/35** (105 assertions) |
| Doc types support | **16** (cccd, national_id_foreign, passport, gpkd, contract VI/EN/ZH, invoice, ...) |
| Field labels VN | **222** |
| Critical files (services + repos + resources) | ~30 PHP files |

```bash
$ git log --oneline | grep -c "\[ai-major\]"   # 11
$ git log --oneline | grep -c "\[ai-assist\]"  # 1
$ git log --oneline | grep -c "\[human\]"      # 2
```

→ Đề bài Section 9.2: *"Đoạn nào AI sinh ra? Team review thế nào?"* — answer: 73% AI-major, review qua PR convention.

<!--
Speaker notes (~45s):
- Số liệu git log thật. 73% commit là AI sinh ra > 70% code (đã review pass).
- 13% human-only (BKM rules manual edit, secrets management, decision dài).
- 7% ai-assist (dev edit nhiều).
- 35 tests pass cover 3 stage độc lập: Stage 4 PII masker, Stage 3 rule validator, OcrPipelineIntegration end-to-end với fake LLM, AnalyzeActions với mock LLM.
- Đề bài hỏi rõ "đoạn nào AI sinh ra, team review thế nào". Em dùng commit prefix convention: `[ai-major]` = AI >70%, dev review pass. `[ai-assist]` = AI <70%, dev edit nhiều. `[human]` = không dùng AI.
-->

---

<!-- _class: lead -->

# Take-aways

🎯 **Skills là tài sản số của team**
Lưu git, tái sử dụng cross-project. 7/10 skill dùng được cho dự án Baokim tiếp theo.

⚙️ **Harness ép AI tuân kỷ luật**
Stage 3a/3b/3c independent · Stage 4 PII mask · Verify math (subtotal+tax=total).

🔄 **AI sai → tự cải thiện qua PR review**
Implicit feedback → LLM analyze → GitHub PR → human approve. Loop khép kín.

💡 **Tận dụng AI thông minh có kiểm soát**
Section 8 đề bài: "API đơn giản, hoạt động tốt > sản phẩm phức tạp lủng củng" ✅

**Cám ơn ban giám khảo.**
Q&A: duynv@baokim.vn · GitHub: baokim/ocr-api (private)

<!--
Speaker notes (~45s):
- 4 take-aways. Đề bài Section 8 nhấn mạnh: đơn giản, hoạt động tốt > phức tạp lủng củng. Em check box này.
- Skills là tài sản số — Anthropic Barry Zhang: "trí thông minh khác chuyên môn, Skills là số hóa chuyên môn".
- Harness — IBM Tejas Kumar: "everything around the model that gives it grounding in reality".
- Loop tự cải thiện — Section 7.5 + 9.4 đề bài.
- Sẵn sàng Q&A.
-->
