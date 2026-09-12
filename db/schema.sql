-- カナメ 見積・実行予算システム スキーマ（正本）
-- 文字コード utf8mb4、日時は DATETIME（TIMESTAMP 不使用）、時刻は JST

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login_id VARCHAR(50) NOT NULL COMMENT 'ログインID',
  name VARCHAR(50) NOT NULL COMMENT '表示名',
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'viewer' COMMENT 'admin/sales/accounting/viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_pw TINYINT(1) NOT NULL DEFAULT 0 COMMENT '初回ログイン時にパスワード変更を求める',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_login (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='利用者';

CREATE TABLE IF NOT EXISTS login_attempts (
  login_id VARCHAR(50) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ログイン失敗回数・ロック';

CREATE TABLE IF NOT EXISTS operation_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(20) NOT NULL COMMENT 'create/update/delete/login',
  area VARCHAR(30) NOT NULL,
  target_id VARCHAR(50) NOT NULL DEFAULT '',
  message VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oplog_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作履歴';

-- ---------- マスタ ----------

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sort_no INT NOT NULL DEFAULT 0,
  name VARCHAR(100) NOT NULL COMMENT '例: タイマルーフＭ型（Excel「データ」の『…』行）',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品カテゴリ（Excel リスト/データ）';

CREATE TABLE IF NOT EXISTS items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL COMMENT 'コード 例 M1, 共仮2, SV-2-3',
  category_id INT UNSIGNED NULL,
  sort_no INT NOT NULL DEFAULT 0,
  name VARCHAR(200) NOT NULL COMMENT '項目',
  material VARCHAR(200) NULL COMMENT '材質',
  length DECIMAL(12,4) NULL COMMENT '長',
  width DECIMAL(12,4) NULL COMMENT '巾',
  thickness DECIMAL(12,4) NULL COMMENT '厚',
  area_unit VARCHAR(20) NULL COMMENT '実数の単位（㎡, m, 式 …）',
  use_unit VARCHAR(20) NULL COMMENT '材料の単位（坪, 枚, 本 …）',
  material_price DECIMAL(14,2) NULL COMMENT '材料単価（使用単位あたり）',
  labor_rate DECIMAL(12,4) NULL COMMENT '歩掛（実数1あたりの人工）',
  labor_unit_price DECIMAL(14,2) NULL COMMENT '人工単価',
  note VARCHAR(500) NULL COMMENT '備考（Excel P列以降）',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_items_code (code),
  KEY idx_items_category (category_id, sort_no),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='単価マスタ（Excel「データ」）';

CREATE TABLE IF NOT EXISTS option_lists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_key VARCHAR(40) NOT NULL COMMENT 'pay_close/pay_day/pay_terms/roof_spec/roof_material/roof_thickness/roof_product/info_source/repeat_kind/work_type/receipt/billing/accounting/bank',
  sort_no INT NOT NULL DEFAULT 0,
  value VARCHAR(200) NOT NULL,
  extra VARCHAR(200) NULL COMMENT '付随値（銀行の手数料率など）',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_option_lists_key (list_key, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='プルダウン選択肢（経理実行予算 59行以降）';

CREATE TABLE IF NOT EXISTS note_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sort_no INT NOT NULL DEFAULT 0,
  body VARCHAR(500) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工事全般特記事項（ひな形）';

CREATE TABLE IF NOT EXISTS budget_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_key VARCHAR(20) NOT NULL COMMENT 'material/subcontract/expense',
  sort_no INT NOT NULL DEFAULT 0,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_budget_items_group (group_key, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='経理実行予算の予算項目（材料費/外注費/経費）';

-- ---------- 案件 ----------

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NULL COMMENT '案件番号 例 H-JY1',
  name VARCHAR(200) NOT NULL COMMENT '件名（工事名）',
  status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft/submitted/ordered/lost/done',
  staff_name VARCHAR(50) NULL COMMENT '担当者',
  budget_date DATE NULL COMMENT '予算組日',
  contract_date DATE NULL COMMENT '契約日',
  estimate_date DATE NULL COMMENT '見積日',
  customer_name VARCHAR(200) NULL COMMENT '見積提出先（宛名）',
  site_name VARCHAR(200) NULL COMMENT '工事場所 名称',
  site_zip VARCHAR(10) NULL,
  site_address VARCHAR(200) NULL,
  site_tel VARCHAR(30) NULL,
  site_fax VARCHAR(30) NULL,
  designer_name VARCHAR(200) NULL COMMENT '設計事務所',
  designer_staff VARCHAR(50) NULL,
  designer_zip VARCHAR(10) NULL,
  designer_address VARCHAR(200) NULL,
  designer_tel VARCHAR(30) NULL,
  designer_fax VARCHAR(30) NULL,
  contractor_name VARCHAR(200) NULL COMMENT 'ゼネコン',
  contractor_staff VARCHAR(50) NULL,
  contractor_zip VARCHAR(10) NULL,
  contractor_address VARCHAR(200) NULL,
  contractor_tel VARCHAR(30) NULL,
  contractor_fax VARCHAR(30) NULL,
  orderer_name VARCHAR(200) NULL COMMENT '発注業者',
  orderer_staff VARCHAR(50) NULL,
  orderer_zip VARCHAR(10) NULL,
  orderer_address VARCHAR(200) NULL,
  orderer_tel VARCHAR(30) NULL,
  orderer_fax VARCHAR(30) NULL,
  period_from DATE NULL COMMENT '契約工期 自',
  period_to DATE NULL COMMENT '契約工期 至',
  start_date DATE NULL COMMENT '着工予定日',
  pay_close VARCHAR(20) NULL COMMENT '締',
  pay_day VARCHAR(20) NULL COMMENT '払',
  pay_cash_pct DECIMAL(5,1) NULL COMMENT '現金 %',
  pay_bill_pct DECIMAL(5,1) NULL COMMENT '手形 %',
  pay_site_days INT NULL COMMENT 'サイト 日',
  pay_terms_text VARCHAR(200) NULL COMMENT '支払条件（見積書に印字）',
  roof_spec VARCHAR(100) NULL,
  roof_material VARCHAR(100) NULL,
  roof_thickness VARCHAR(20) NULL,
  roof_product VARCHAR(100) NULL,
  roof_color VARCHAR(100) NULL,
  roof_area DECIMAL(12,2) NULL COMMENT '㎡',
  work_type VARCHAR(100) NULL COMMENT '工事種別',
  info_source VARCHAR(100) NULL COMMENT '情報源',
  repeat_kind VARCHAR(20) NULL COMMENT '新規/リピート',
  note_receipt VARCHAR(100) NULL COMMENT '備考 領収書',
  note_billing VARCHAR(100) NULL COMMENT '備考 請求先',
  note_accounting VARCHAR(100) NULL COMMENT '計上',
  payment_due_date DATE NULL COMMENT '入金予定日',
  remarks TEXT NULL,
  -- 見積計算の設定（明細書 P3 / X3 と営業実行予算の率）
  uniform_labor_price DECIMAL(14,2) NULL COMMENT '一律人工設定（空なら品目の人工単価）',
  uniform_rate DECIMAL(6,4) NOT NULL DEFAULT 0.8000 COMMENT '一律掛率',
  general_admin_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0800 COMMENT '一般管理費率',
  site_admin_rate DECIMAL(6,4) NOT NULL DEFAULT 0.1000 COMMENT '現場管理費率',
  fee_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0300 COMMENT '手数料率（契約予定金額に対する）',
  reserve_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0150 COMMENT '予備費率',
  design_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0500 COMMENT '設計監理費率',
  office_rate DECIMAL(6,4) NOT NULL DEFAULT 0.1000 COMMENT '事業所経費率',
  disposal_amount DECIMAL(14,0) NULL COMMENT '材工費（処分費）',
  site_expense_amount DECIMAL(14,0) NULL COMMENT '現場経費',
  planned_contract_amount DECIMAL(14,0) NULL COMMENT '契約予定金額（営業実行予算 B24）',
  -- 経理実行予算 金額欄
  estimate_amount DECIMAL(14,0) NULL COMMENT '見積金額',
  contract_amount DECIMAL(14,0) NULL COMMENT '契約金額',
  extra1_amount DECIMAL(14,0) NULL COMMENT '追加工事1',
  extra2_amount DECIMAL(14,0) NULL COMMENT '追加工事2',
  tax_rate DECIMAL(5,4) NOT NULL DEFAULT 0.1000,
  insurance_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0050 COMMENT '労災保険料率',
  supervision_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0500 COMMENT '工事監理費率',
  budget_office_rate DECIMAL(6,4) NOT NULL DEFAULT 0.1000 COMMENT '事業所経費率（経理）',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_projects_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件（経理実行予算ヘッダー＋設定）';

CREATE TABLE IF NOT EXISTS project_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  name VARCHAR(100) NOT NULL COMMENT '工事項目 例 【屋根工事】',
  loss_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0700 COMMENT 'ロス率',
  PRIMARY KEY (id),
  KEY idx_sections_project (project_id, sort_no),
  CONSTRAINT fk_sections_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='明細書の工事項目ブロック（P1〜）';

CREATE TABLE IF NOT EXISTS project_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id INT UNSIGNED NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  item_id INT UNSIGNED NULL,
  item_code VARCHAR(30) NULL COMMENT '選択時のコード（マスタ削除後も残す）',
  name VARCHAR(200) NULL COMMENT '項目（マスタから写し。手入力可）',
  material VARCHAR(200) NULL,
  length DECIMAL(12,4) NULL,
  width DECIMAL(12,4) NULL,
  thickness DECIMAL(12,4) NULL,
  area_unit VARCHAR(20) NULL,
  use_unit VARCHAR(20) NULL,
  material_price DECIMAL(14,2) NULL,
  labor_rate DECIMAL(12,4) NULL,
  labor_unit_price DECIMAL(14,2) NULL,
  quantity DECIMAL(12,2) NULL COMMENT '実数',
  is_quote TINYINT(1) NOT NULL DEFAULT 1 COMMENT '見積書に行として出す',
  merge_into_line_id INT UNSIGNED NULL COMMENT '合算先の行（is_quote=0 のとき）',
  rate_override DECIMAL(6,4) NULL COMMENT '行別掛率（空なら一律掛率）',
  print_name VARCHAR(200) NULL COMMENT '名称編集',
  print_material VARCHAR(200) NULL COMMENT '左枠編集',
  remarks VARCHAR(200) NULL,
  PRIMARY KEY (id),
  KEY idx_lines_section (section_id, sort_no),
  CONSTRAINT fk_lines_section FOREIGN KEY (section_id) REFERENCES project_sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='明細書の行';

CREATE TABLE IF NOT EXISTS extra_works (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  no TINYINT NOT NULL COMMENT '1〜4',
  title VARCHAR(100) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_extra_works (project_id, no),
  CONSTRAINT fk_extra_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='その他工事1〜4';

CREATE TABLE IF NOT EXISTS extra_work_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  extra_work_id INT UNSIGNED NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  name VARCHAR(200) NULL,
  quantity DECIMAL(12,2) NULL,
  unit VARCHAR(20) NULL,
  unit_price DECIMAL(14,2) NULL,
  PRIMARY KEY (id),
  KEY idx_extra_lines (extra_work_id, sort_no),
  CONSTRAINT fk_extra_lines FOREIGN KEY (extra_work_id) REFERENCES extra_works (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='その他工事の行';

CREATE TABLE IF NOT EXISTS project_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  sort_no INT NOT NULL DEFAULT 0,
  body VARCHAR(500) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notes_project (project_id, sort_no),
  CONSTRAINT fk_notes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件ごとの特記事項';

CREATE TABLE IF NOT EXISTS project_budgets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  budget_item_id INT UNSIGNED NOT NULL,
  sales_amount DECIMAL(14,0) NULL COMMENT '営業予算',
  work_amount DECIMAL(14,0) NULL COMMENT '工事予算',
  PRIMARY KEY (id),
  UNIQUE KEY uk_budgets (project_id, budget_item_id),
  CONSTRAINT fk_budgets_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_budgets_item FOREIGN KEY (budget_item_id) REFERENCES budget_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='経理実行予算の金額';
