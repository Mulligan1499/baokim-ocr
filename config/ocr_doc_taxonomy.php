<?php

/**
 * Taxonomy 13 doc types KSNB Baokim — list field cần bóc tách theo khảo sát.
 *
 * Mỗi doc type 2 list:
 *  - critical: bắt buộc phải có. Sai format → confidence cap 0.4. Weight 2 trong
 *    overall confidence (Stage 5 aggregator).
 *  - normal: mong đợi. Liệt kê trong prompt Stage 2 để model biết tìm.
 *    Không có thì OK. Weight 1.
 *
 * Model luôn được phép emit field NGOÀI 2 list (freeform) nếu thấy trong tài liệu.
 *
 * Source: [.claude/references/KHAO-SAT-KSNB-INSIGHTS.md](.claude/references/KHAO-SAT-KSNB-INSIGHTS.md)
 *  - Respondent 1 (chứng từ thương mại): hợp đồng, hóa đơn, vận đơn, tờ khai HQ, AML
 *  - Respondent 2 (CCCD/GPKD/Export CD)
 *  - Respondent 3 (toàn diện — hợp đồng + danh mục hàng hóa chi tiết + giấy ủy quyền + HĐLĐ)
 *
 * Sửa file này khi:
 *  - Agent `ocr:analyze-actions` phát hiện pattern E (field skip rate >80%) → có thể drop
 *  - KSNB ra thêm doc type mới → add entry
 *  - Field name conflict → coordinate với [config/ocr_field_labels.php](config/ocr_field_labels.php)
 */
return [

    // ========== P0 — gặp ở cả 3 KSNB, MUST work ==========

    'cccd' => [
        'label' => 'CCCD/CMND',
        'critical' => [
            'so_cccd', 'ho_ten', 'ngay_sinh', 'ngay_cap',
        ],
        'normal' => [
            'noi_cap', 'co_quan_cap', 'gioi_tinh', 'quoc_tich', 'dan_toc',
            'ton_giao', 'que_quan', 'noi_thuong_tru', 'co_gia_tri_den',
            'dac_diem_nhan_dang',
        ],
    ],

    'passport' => [
        'label' => 'Hộ chiếu',
        'critical' => [
            'passport_number', 'full_name', 'date_of_birth', 'expiry_date', 'nationality',
        ],
        'normal' => [
            'surname', 'given_name', 'place_of_birth', 'date_of_issue',
            'place_of_issue', 'issuing_authority', 'sex',
        ],
    ],

    'gpkd' => [
        'label' => 'Giấy phép kinh doanh',
        'critical' => [
            'mst', 'ten_doanh_nghiep', 'dia_chi', 'nguoi_dai_dien', 'ngay_cap',
        ],
        'normal' => [
            'ten_cong_ty_tieng_anh', 'chuc_vu', 'ngay_thanh_lap', 'ngay_het_han',
            'nganh_nghe', 'von_dieu_le', 'loai_hinh_doanh_nghiep', 'so_giay_phep',
        ],
    ],

    'contract_vi' => [
        'label' => 'Hợp đồng (tiếng Việt)',
        'critical' => [
            'ben_a', 'ben_b', 'ngay_ky', 'gia_tri',
        ],
        'normal' => [
            'so_hop_dong', 'ngay_hop_dong', 'thoi_han_hop_dong',
            'mst_ben_a', 'mst_ben_b',
            'dia_chi_ben_a', 'dia_chi_ben_b',
            'nguoi_dai_dien_ben_a', 'nguoi_dai_dien_ben_b',
            // Khảo sát R1+R2+R3: các điều khoản
            'dieu_khoan_thanh_toan', 'dieu_khoan_van_chuyen', 'dieu_khoan_giao_hang',
            'dieu_khoan_trach_nhiem',
            // Danh mục hàng hóa (R3 chi tiết)
            'danh_muc_hang_hoa', 'mo_ta_hang_hoa', 'so_luong', 'don_gia', 'tong_gia_tri',
            'don_vi_do_luong',
            // Thông tin thanh toán
            'thong_tin_tai_khoan_thu_huong', 'ngan_hang_thanh_toan',
            'so_tai_khoan', 'chu_tai_khoan',
        ],
    ],

    'contract_en' => [
        'label' => 'Hợp đồng (tiếng Anh)',
        'critical' => [
            'party_a', 'party_b', 'signing_date', 'value',
        ],
        'normal' => [
            'contract_number', 'contract_date', 'contract_term', 'effective_date',
            'party_a_tax_code', 'party_b_tax_code',
            'party_a_address', 'party_b_address',
            'party_a_representative', 'party_b_representative',
            // Terms
            'payment_term', 'shipment_term', 'delivery_term', 'liability_term',
            // Goods
            'goods_description', 'item_description', 'model', 'quantity', 'unit_price',
            'total_value', 'currency', 'unit_of_measurement',
            // Payment info
            'payment_bank_name', 'payment_bank_account_name', 'payment_bank_account_number',
            'swift_code',
        ],
    ],

    'contract_zh' => [
        'label' => 'Hợp đồng (tiếng Trung)',
        'critical' => [
            'party_a', 'party_b', 'signing_date', 'value',
        ],
        'normal' => [
            // Same as contract_en — Hán-Việt phonetic gắn vào value_translated_vi per field
            'contract_number', 'contract_date', 'contract_term', 'effective_date',
            'party_a_tax_code', 'party_b_tax_code',
            'party_a_address', 'party_b_address',
            'party_a_representative', 'party_b_representative',
            'payment_term', 'shipment_term', 'delivery_term', 'liability_term',
            'goods_description', 'item_description', 'model', 'quantity', 'unit_price',
            'total_value', 'currency', 'unit_of_measurement',
            'payment_bank_name', 'payment_bank_account_name', 'payment_bank_account_number',
            'swift_code',
        ],
    ],

    'invoice' => [
        'label' => 'Hóa đơn',
        'critical' => [
            'invoice_number', 'total_amount', 'invoice_date', 'mst',
        ],
        'normal' => [
            'seller_name', 'buyer_name', 'seller_tax_code', 'buyer_tax_code',
            'seller_address', 'buyer_address',
            'subtotal', 'tax_amount', 'tax_rate', 'currency',
            // Items
            'goods_description', 'item_description', 'model', 'quantity', 'unit_price',
            'unit_of_measurement',
            // Terms
            'payment_term', 'delivery_term', 'shipment_term',
            'payment_bank_name', 'payment_bank_account_number',
        ],
    ],

    // ========== P1 — gặp ở 2/3 KSNB, should work ==========

    'legal_doc' => [
        'label' => 'Văn bản pháp lý',
        'critical' => [
            'document_number', 'issuing_authority', 'issue_date',
        ],
        'normal' => [
            'subject', 'effective_date', 'expiry_date', 'parties', 'scope',
        ],
    ],

    'customs_declaration' => [
        'label' => 'Tờ khai hải quan',
        'critical' => [
            'declaration_number', 'declaration_date', 'importer', 'exporter', 'hs_code',
        ],
        'normal' => [
            'pre_declaration_number', 'gross_weight_kg', 'net_weight_kg',
            'container_number', 'number_of_packages', 'package_type',
            'transport_method', 'transaction_method',
            'origin_country_region', 'destination_country_region', 'final_destination_country_region',
            'port_of_destination', 'customs_office_of_exit', 'supervision_method',
            'exemption_nature', 'trade_country_region', 'domestic_source_location',
            'transport_vehicle_name_voyage_number', 'contract_agreement_number',
            'declaration_unit', 'entrusting_party', 'entrusted_party',
            'goods_description', 'unit_price_total_price_currency', 'bill_of_lading_number',
        ],
    ],

    'bill_of_lading' => [
        'label' => 'Vận đơn',
        'critical' => [
            'bl_number', 'shipper', 'consignee', 'vessel_name', 'container_number',
        ],
        'normal' => [
            'bl_date', 'notify_party', 'carrier', 'voyage_number',
            'port_of_loading', 'port_of_discharge',
            'place_of_receipt', 'place_of_delivery',
            'goods_description', 'gross_weight', 'net_weight', 'quantity', 'package_type',
        ],
    ],

    // ========== P2 — gặp ở 1 KSNB nhưng scope rộng, best effort ==========

    'aml_charter' => [
        'label' => 'Điều lệ AML',
        'critical' => [
            'document_number', 'effective_date', 'issuing_authority',
        ],
        'normal' => [
            'company_name', 'scope', 'effective_date', 'expiry_date',
        ],
    ],

    'power_of_attorney' => [
        'label' => 'Giấy ủy quyền',
        'critical' => [
            'principal', 'attorney', 'scope', 'effective_date', 'expiry_date',
        ],
        'normal' => [
            'document_number', 'document_date', 'principal_address', 'attorney_address',
            'witnesses', 'work_scope',
        ],
    ],

    'labor_contract' => [
        'label' => 'Hợp đồng lao động',
        'critical' => [
            'employer', 'employee', 'position', 'salary', 'signing_date',
        ],
        'normal' => [
            'contract_number', 'contract_term', 'contract_start_date', 'contract_end_date',
            'working_hours', 'work_location', 'employer_address',
            'employee_id_number', 'employer_mst',
            'payment_method', 'payment_date',
            'trial_period_salary', 'trial_period_duration',
            'benefits', 'job_scope', 'job_description',
        ],
    ],

    'financial_report' => [
        'label' => 'Báo cáo tài chính',
        'critical' => [
            'reporting_period', 'total_revenue', 'net_profit', 'total_assets', 'currency',
        ],
        'normal' => [
            'company_name', 'company_tax_code', 'total_liabilities', 'equity',
            'cost_of_goods_sold', 'operating_expenses', 'gross_profit',
            'report_date', 'auditor',
        ],
    ],

    // ========== Fallback ==========

    'other' => [
        'label' => 'Khác',
        'critical' => [],
        'normal' => [],
        // Model tự do extract bất cứ field gì thấy
    ],

];
