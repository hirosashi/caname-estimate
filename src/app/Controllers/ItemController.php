<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\OperationLog;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

final class ItemController extends Base
{
    public static function index(): void
    {
        Auth::requireLogin();
        $q = App::param('q');
        $cat = App::intParam('category_id', 0);
        $sql = 'SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (i.code LIKE ? OR i.name LIKE ? OR i.material LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($cat > 0) {
            $sql .= ' AND i.category_id = ?';
            $params[] = $cat;
        }
        $sql .= ' ORDER BY c.sort_no, i.sort_no, i.id LIMIT 500';
        View::render('items/index', [
            'title' => '単価マスタ',
            'rows' => Db::rows($sql, $params),
            'q' => $q,
            'category_id' => $cat,
            'categories' => Db::rows('SELECT * FROM categories ORDER BY sort_no, id'),
        ]);
    }

    /** 明細書の品目検索（JSON） */
    public static function search(): void
    {
        Auth::requireLogin();
        $q = App::param('q');
        $cat = App::intParam('category_id', 0);
        $sql = 'SELECT i.code, i.name, i.material, i.length, i.width, i.thickness, i.area_unit, i.use_unit, i.material_price, i.labor_rate, i.labor_unit_price, c.name AS category_name
                FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.is_active = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (i.code LIKE ? OR i.name LIKE ? OR i.material LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($cat > 0) {
            $sql .= ' AND i.category_id = ?';
            $params[] = $cat;
        }
        $sql .= ' ORDER BY c.sort_no, i.sort_no, i.id LIMIT 200';
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(Db::rows($sql, $params), JSON_UNESCAPED_UNICODE);
    }

    public static function edit(): void
    {
        Auth::requireLogin();
        $id = App::intParam('id', 0);
        $item = $id > 0 ? Db::row('SELECT * FROM items WHERE id = ?', [$id]) : null;
        if ($id > 0 && $item === null) {
            Session::flash('error', '品目が見つかりません。');
            App::redirect('/items');
        }
        View::render('items/edit', [
            'title' => $item === null ? '品目の追加' : '品目の編集',
            'item' => $item ?? ['id' => 0, 'code' => '', 'category_id' => App::intParam('category_id', 0), 'name' => '', 'material' => '', 'length' => null, 'width' => null, 'thickness' => null, 'area_unit' => '㎡', 'use_unit' => '', 'material_price' => null, 'labor_rate' => null, 'labor_unit_price' => null, 'note' => '', 'is_active' => 1, 'sort_no' => 0],
            'categories' => Db::rows('SELECT * FROM categories ORDER BY sort_no, id'),
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('items', '/items');
        $id = App::intParam('id', 0);
        $v = new Validator($_POST);
        $v->required('code', 'コード')->maxLength('code', 30, 'コード')->required('name', '項目')->maxLength('name', 200, '項目')
            ->number('length', '長')->number('width', '巾')->number('thickness', '厚')
            ->number('material_price', '材料単価')->number('labor_rate', '歩掛')->number('labor_unit_price', '人工単価');
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
            App::redirect($id > 0 ? '/items/edit?id=' . $id : '/items/edit');
        }
        $code = App::param('code');
        $dup = Db::row('SELECT id FROM items WHERE code = ? AND id <> ?', [$code, $id]);
        if ($dup !== null) {
            Session::flash('error', "コード「{$code}」は既に使われています。");
            App::redirect($id > 0 ? '/items/edit?id=' . $id : '/items/edit');
        }
        $data = [
            'code' => $code,
            'category_id' => App::intParam('category_id', 0) ?: null,
            'name' => App::param('name'),
            'material' => Validator::toStr($_POST['material'] ?? null),
            'length' => Validator::toNum($_POST['length'] ?? null),
            'width' => Validator::toNum($_POST['width'] ?? null),
            'thickness' => Validator::toNum($_POST['thickness'] ?? null),
            'area_unit' => Validator::toStr($_POST['area_unit'] ?? null),
            'use_unit' => Validator::toStr($_POST['use_unit'] ?? null),
            'material_price' => Validator::toNum($_POST['material_price'] ?? null),
            'labor_rate' => Validator::toNum($_POST['labor_rate'] ?? null),
            'labor_unit_price' => Validator::toNum($_POST['labor_unit_price'] ?? null),
            'note' => Validator::toStr($_POST['note'] ?? null),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_no' => App::intParam('sort_no', 0),
            'updated_at' => Clock::nowStr(),
        ];
        if ($id > 0) {
            $sets = implode(', ', array_map(static fn(string $k): string => "$k = :$k", array_keys($data)));
            Db::exec("UPDATE items SET $sets WHERE id = :id", $data + ['id' => $id]);
            OperationLog::write('update', 'items', (string)$id, '品目更新: ' . $code);
        } else {
            if ($data['sort_no'] === 0) {
                $data['sort_no'] = (int)(Db::value('SELECT COALESCE(MAX(sort_no),0)+1 FROM items WHERE category_id <=> ?', [$data['category_id']]) ?? 1);
            }
            $data['created_at'] = $data['updated_at'];
            $k = array_keys($data);
            Db::exec('INSERT INTO items (' . implode(', ', $k) . ') VALUES (:' . implode(', :', $k) . ')', $data);
            $id = Db::lastId();
            OperationLog::write('create', 'items', (string)$id, '品目追加: ' . $code);
        }
        Session::flash('ok', '品目を保存しました。');
        App::redirect('/items?q=' . rawurlencode($code));
    }

    public static function delete(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('items', '/items');
        $id = App::intParam('id', 0);
        $item = Db::row('SELECT code FROM items WHERE id = ?', [$id]);
        if ($item !== null) {
            $used = (int)Db::value('SELECT COUNT(*) FROM project_lines WHERE item_id = ?', [$id]);
            if ($used > 0) {
                Db::exec('UPDATE items SET is_active = 0, updated_at = ? WHERE id = ?', [Clock::nowStr(), $id]);
                Session::flash('ok', "明細で使用中のため、無効化しました（{$used}行）。");
            } else {
                Db::exec('DELETE FROM items WHERE id = ?', [$id]);
                Session::flash('ok', '品目を削除しました。');
            }
            OperationLog::write('delete', 'items', (string)$id, '品目削除/無効化: ' . $item['code']);
        }
        App::redirect('/items');
    }

    public static function categories(): void
    {
        Auth::requireLogin();
        View::render('items/categories', [
            'title' => 'カテゴリ',
            'rows' => Db::rows('SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count FROM categories c ORDER BY c.sort_no, c.id'),
        ]);
    }

    public static function saveCategories(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('items', '/categories');
        $names = self::postArray('name');
        $ids = self::postArray('id');
        Db::tx(static function () use ($names, $ids): void {
            $n = 0;
            foreach ($names as $i => $name) {
                $s = Validator::toStr($name);
                if ($s === null) {
                    continue;
                }
                $n++;
                $id = (int)($ids[$i] ?? 0);
                if ($id > 0) {
                    Db::exec('UPDATE categories SET name = ?, sort_no = ? WHERE id = ?', [mb_substr($s, 0, 100), $n, $id]);
                } else {
                    Db::exec('INSERT INTO categories (sort_no, name) VALUES (?,?)', [$n, mb_substr($s, 0, 100)]);
                }
            }
        });
        OperationLog::write('update', 'items', '-', 'カテゴリ保存');
        Session::flash('ok', 'カテゴリを保存しました。');
        App::redirect('/categories');
    }
}
