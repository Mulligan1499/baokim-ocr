Khảo sát KSNB — Raw Data + Insights

Khảo sát 3 nhân viên KSNB Baokim (Kiểm Soát Nội Bộ) về workflow xử lý tài liệu onboarding merchant.
Dữ liệu này là source of truth cho mọi quyết định scope/priority trong dự án OCR.


Tóm tắt nhanh (5 insights cho demo)

Tiếng Trung 10-30% là core requirement — KHÔNG phải edge case. Loại vendor không support tiếng Trung (FPT.AI) là quyết định data-driven, không cảm tính.
Tiếng Việt thực ra LOW — chỉ 10-30% (P1) đến <10% (P2). Bài toán đa ngôn ngữ, không phải VN-first.
Volume thực tế 50-100 tài liệu/tuần/người — không quá nhiều, đủ để justify automation nhưng không phải massive scale.
2/3 KSNB yêu cầu confidence chi tiết PER FIELD — quyết định Stage 5 Aggregator + flagged_low_confidence per field.
Latency acceptance cao — 2/3 OK với >30s nếu chính xác; chỉ 1/3 muốn <15s. Sync API là đủ cho v1, không cần async.


Respondent 1 — KSNB chuyên xử lý chứng từ thương mại
Volume + Loại tài liệu

Volume: 50-100 tài liệu/tuần
Loại thường gặp (≥1 lần/tuần):

CCCD/CMND, Hộ chiếu
Giấy phép kinh doanh
Hợp đồng tiếng Việt + tiếng Anh/Trung
Hóa đơn / chứng từ thanh toán
Văn bản pháp lý nước ngoài
Báo cáo tài chính
Tờ khai hải quan
Vận đơn
Văn bản điều lệ Công ty
Điều lệ AML của Công ty



Định dạng nhận về

File scan từ máy scan
PDF gốc (Word/Excel export)
PDF scan hoặc chụp lại
File Word / Excel (không phải OCR thuần, có text extraction)

Vấn đề thường gặp

Tài liệu mờ, không rõ chữ
Tài liệu dài (>10 trang)

Tỷ lệ ngôn ngữ

Tiếng Việt: 10-30%
Tiếng Anh: >50% (cao nhất)
Tiếng Trung: 10-30%

Khi xử lý tài liệu ngoại ngữ

Dịch toàn bộ nội dung sang tiếng Việt
Chỉ dịch các trường thông tin quan trọng (tên, số, ngày, địa chỉ)

Trường thông tin cần bóc tách
Hợp đồng thương mại:

Tên đối tác 2 bên, MST 2 bên
Mô tả hàng hóa: Model, số lượng, đơn giá, tổng giá trị
Điều khoản thanh toán (payment term)
Điều khoản vận chuyển (delivery term)
Điều khoản về trách nhiệm các bên

Hóa đơn thương mại:

Tên đối tác 2 bên, MST 2 bên
Mô tả hàng hóa: Model, số lượng, đơn giá, tổng giá trị
Điều khoản thanh toán + vận chuyển

Vận đơn:

Shipper & Consignee là ai
Tên hãng tàu, mã tàu
Mô tả hàng hóa: Model, số lượng, trọng lượng
Mã vận đơn, ngày issue
Carrier là ai

Tờ khai hải quan:

Các thông tin trên tờ khai có đối chiếu được với hợp đồng, hóa đơn, vận đơn hay không
Ngày của tờ khai, số tờ khai

Điều lệ Công ty / AML:

Thông thường toàn bằng tiếng Anh và rất dài
Tốn nhiều thời gian vừa dịch vừa kiểm tra logic của điều khoản

Pain point đặc biệt: "Các chứng từ trên mà bằng tiếng Trung: Tốn rất nhiều thời gian chuyển full văn bản để check, đặc biệt là tờ khai hải quan Xuất khẩu Trung Quốc"
Thời gian + UX

Thời gian xử lý 1 tài liệu hiện tại: 10-20 phút
Chấp nhận chờ OCR: >30 giây cũng được nếu kết quả chính xác
Confidence thấp (ảnh mờ): "Vẫn trả kết quả kèm cảnh báo 'cần kiểm tra lại', tôi tự đối chiếu"
Sau khi nhận kết quả OCR:

Copy-paste vào hệ thống nội bộ Baokim
Nhập vào file Excel theo dõi
Lưu lại để đối chiếu khi cần




Respondent 2 — KSNB tập trung CCCD/GPKD/Export CD
Volume + Loại tài liệu

Volume: 50-100 tài liệu/tuần
Loại thường gặp:

CCCD/CMND, Hộ chiếu
Giấy phép kinh doanh
Hợp đồng (cả VN + EN/ZH)
Hóa đơn / chứng từ thanh toán
Văn bản pháp lý nước ngoài



Định dạng nhận về

Ảnh chụp bằng điện thoại (nhiều vấn đề về chất lượng)
File scan từ máy scan
PDF scan hoặc chụp lại

Vấn đề thường gặp (NHIỀU)

Tài liệu mờ, không rõ chữ
Tài liệu nghiêng hoặc xoay
Thiếu sáng hoặc quá tối
Bị che một phần (dấu mộc che chữ, ngón tay che...)
Tài liệu nhăn, gấp
Tài liệu dài (>10 trang)

Tỷ lệ ngôn ngữ

Tiếng Việt: <10% (rất thấp!)
Tiếng Anh: 30-50%
Tiếng Trung: >30% (cao nhất trong 3 người)

Khi xử lý tài liệu ngoại ngữ

Dịch toàn bộ nội dung sang tiếng Việt

Trường thông tin cần bóc tách
CCCD:

Số căn cước, họ tên, ngày sinh, địa chỉ thường trú
Ngày cấp, nơi cấp, hiệu lực

Giấy phép kinh doanh:

Mã số doanh nghiệp, tên công ty, địa chỉ trụ sở
Người đại diện pháp luật
Ngày cấp - hết hạn
Ngành nghề

Hợp đồng:

Ngày ký, các bên, giá trị, thời hạn
Các điều khoản (Payment, Shipment, thông tin thanh toán)

Export CD (tờ khai xuất khẩu):

Mã chứng từ
Loại hàng hoá
Mã hàng hoá
Ngày cấp
Ngày rời cảng
Tổng trọng lượng

Thời gian + UX

Thời gian xử lý hiện tại: 5-10 phút
Chấp nhận chờ OCR: 5-15 giây (nhanh nhất trong 3 người)
Confidence thấp: "Trả kết quả kèm chỉ số tin cậy chi tiết theo TỪNG TRƯỜNG để tôi biết check chỗ nào"
Sau khi nhận kết quả:

Copy-paste vào hệ thống Baokim
Nhập vào file Excel theo dõi




Respondent 3 — KSNB toàn diện, scope rộng nhất
Volume + Loại tài liệu

Volume: 50-100 tài liệu/tuần
Loại thường gặp (rộng nhất):

CCCD/CMND, Hộ chiếu
Giấy phép kinh doanh
Hợp đồng (VN + EN/ZH)
Hóa đơn / chứng từ thanh toán
Văn bản pháp lý nước ngoài
Chứng từ vận chuyển: Bill of Lading, Export CD
Giấy ủy quyền
Hợp đồng lao động



Định dạng nhận về

Ảnh chụp bằng điện thoại
File scan từ máy scan
PDF gốc (Word/Excel)
PDF scan
File Word / Excel (có text extraction)

Vấn đề thường gặp

Tài liệu mờ, không rõ chữ
Tài liệu nghiêng hoặc xoay

Tỷ lệ ngôn ngữ

Tiếng Việt: 30-50% (cao nhất trong 3 người)
Tiếng Anh: 30-50%
Tiếng Trung: 10-30%

Khi xử lý tài liệu ngoại ngữ

Dịch toàn bộ nội dung sang tiếng Việt

Trường thông tin cần bóc tách (DETAILED NHẤT)
CCCD/Hộ chiếu:

Số CCCD/Hộ chiếu, họ tên
Ngày sinh, ngày hết hạn

ĐKKD:

Mã số DN, tên cty
Tên người đại diện PL
Ngày thành lập
Ngành nghề KD

Hợp đồng:

Số hợp đồng, ngày hợp đồng
Thông tin các bên liên quan
Danh mục hàng hóa/dịch vụ:

Tên, chủng loại, đơn giá, giá trị
Đơn vị đo lường, giá trị đo lường


Điều khoản thanh toán
Điều khoản giao hàng
Thông tin tài khoản thụ hưởng

Hóa đơn:

Số hóa đơn, ngày hóa đơn
Thông tin các bên liên quan
Danh mục hàng hóa/dịch vụ (same as hợp đồng)

Chứng từ vận chuyển:

Bill of Lading: số BL, ngày BL, điểm đến, điểm đi, số container, thông tin các bên, danh mục hàng hóa
Export CD: số export CD, thông tin các bên, danh mục hàng hóa, điều khoản giao hàng

Giấy ủy quyền:

Thông tin các bên liên quan
Nội dung phạm vi
Công việc ủy quyền
Thời hạn ủy quyền (nếu có)

Hợp đồng lao động:

Số HĐLĐ, ngày HĐLĐ
Thông tin các bên liên quan
Thời hạn HĐLĐ
Phạm vi công việc của người lao động

Thời gian + UX

Thời gian xử lý hiện tại: 5-10 phút
Chấp nhận chờ OCR: >30 giây nếu chính xác
Confidence thấp: "Trả kết quả kèm chỉ số tin cậy chi tiết theo TỪNG TRƯỜNG để tôi biết check chỗ nào"
Sau khi nhận kết quả:

Lưu lại để đối chiếu khi cần
Nhập vào bảng tính Google Sheet để tổng hợp và theo dõi




Aggregated Insights — implications cho dự án
Loại tài liệu cần support (consolidated từ 3 người)
P0 (must work hoàn hảo) — gặp ở cả 3 người:

CCCD / CMND
Hộ chiếu / Passport
Giấy phép kinh doanh
Hợp đồng tiếng Việt
Hợp đồng tiếng Anh/Trung
Hóa đơn / chứng từ thanh toán

P1 (should work, có warning OK) — gặp ở 2/3 người:
7. Văn bản pháp lý nước ngoài
8. Tờ khai hải quan / Export CD
9. Vận đơn / Bill of Lading
P2 (best effort) — gặp ở 1 người nhưng có scope rộng:
10. Điều lệ Công ty / AML policy
11. Giấy ủy quyền
12. Hợp đồng lao động
13. Báo cáo tài chính
14. Other (fallback)
→ Tổng 13 doc types + 1 fallback = enum 14 giá trị cho document_type (file 02 Section 5).
Critical fields (cross-reference 3 KSNB) — weight 2 trong Confidence Aggregator
Các field này cả 3 đều nhắc → KHÔNG được phép sai:
FieldLý do criticalid_number (CCCD)12 digits, định danh cá nhânpassport_numberĐịnh danh cá nhân quốc tếtax_code (MST)10/13 digits, định danh doanh nghiệpfull_name (party_a_name, party_b_name)Đối tác giao dịchsigning_date / invoice_date / bl_dateTính hiệu lựctotal_amount / unit_priceGiá trị giao dịchbl_number / invoice_number / contract_numberĐịnh danh chứng từcontainer_number (vận đơn)Định danh hàng hoá vận chuyểnaccount_numberThông tin thanh toán
UX implications
Khía cạnhDecision dựa khảo sátConfidence per fieldBẮT BUỘC. 2/3 KSNB explicit yêu cầu. Stage 5 Aggregator output per-field + overallLatency targetp95 ≤ 8s đủ (2/3 OK >30s, 1/3 muốn 5-15s)Sync vs asyncSync OK cho v1. Không ai nói cần async/batchWarning system3/3 đều cần. Implement warnings: LOW_CONFIDENCE, MEDIUM_CONFIDENCE, CRITICAL_FIELD_FAILED, LOW_IMAGE_QUALITYOutput post-processingKSNB copy-paste vào hệ thống Baokim + Google Sheet → response cần raw, KHÔNG mask PII
Language strategy
Ngôn ngữRange tỷ lệStrategyTiếng Việt<10% đến 30-50%Extract trực tiếp, không cần translateTiếng Anh30-50% đến >50%Extract + translate fields critical sang VNTiếng Trung10-30% đến >30%Extract + translate toàn bộ + Hán-Việt phonetic cho tên
→ Multi-language là core, không phải edge case. Vendor không support tiếng Trung → loại (lý do FPT.AI bị loại trong tech-decisions Section 2).
Image quality strategy
P2 KSNB list 6 vấn đề chất lượng ảnh (mờ, nghiêng, thiếu sáng, bị che, nhăn, dài).
→ Stage 1 Classifier phải có image_quality_note mạnh để Stage 2 biết khi nào trả value="" thay vì cố đọc.
→ Anti-hallucination pattern AH-7 (negative few-shot) là single point of failure.