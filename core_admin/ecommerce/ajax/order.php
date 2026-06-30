<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
if (empty($isAdmin)) {
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}
?>
<?php
// Return JSON for uncaught errors instead of blank 500
register_shutdown_function(function () {
	$err = error_get_last();
	if (!$err) return;
	$fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
	if (!in_array($err['type'], $fatal, true)) return;
	while (ob_get_level()) { @ob_end_clean(); }
	http_response_code(500);
	echo json_encode([
		'ok' => false,
		'msg' => 'Server error',
		'error' => $err['message'],
		'file' => basename($err['file'] ?? ''),
		'line' => $err['line'] ?? 0,
	], JSON_UNESCAPED_UNICODE);
	exit;
});

function buildCartHtmlFromRow(array $row) {
	// Supports both legacy schema (product) and newer schema (products_json)
	$html = [];

	if (!empty($row['products_json'])) {
		$items = json_decode($row['products_json'], true);
		if (is_array($items)) {
			foreach ($items as $item) {
				$name = h($item['name'] ?? 'Sản phẩm');
				$variant = trim((string)($item['variant'] ?? ''));
				$variantBadge = $variant !== '' ? " <span class='text-muted small'>(" . h($variant) . ")</span>" : '';
				$qty = (string)($item['qty'] ?? '');
				$qtyBadge = $qty !== '' ? " <span class='badge bg-light text-dark ms-1'>x" . h($qty) . "</span>" : '';
				$html[] = "<div class='cart-line'><span class='fw-semibold text-primary'>{$name}</span>{$variantBadge}{$qtyBadge}</div>";
			}
		}
	}

	if (!$html) {
		$productStr = (string)($row['product'] ?? '');
		$segments = preg_split('/\r\n|\r|\n|\||,/', $productStr);
		$segments = array_filter(array_map('trim', $segments), fn($val) => $val !== '');
		foreach ($segments as $segment) {
			$html[] = "<div class='cart-line'><span class='fw-semibold text-primary'>" . h($segment) . "</span></div>";
		}
	}

	if (!$html) {
		return "<span class='text-muted small'>Không có sản phẩm</span>";
	}

	return implode('', $html);
}

function orderExists(mysqli $ithanhloc, string $orderId): bool {
	$stmt = $ithanhloc->prepare('SELECT 1 FROM ecommerce_order WHERE order_id = ? LIMIT 1');
	$stmt->bind_param('s', $orderId);
	$stmt->execute();
	$stmt->store_result();
	$has = $stmt->num_rows > 0;
	$stmt->close();
	return $has;
}


function normalizeProductsJson($raw): string {
	$raw = (string)$raw;
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : '[]';
}

function payloadHas(array $payload, string $key): bool {
	return array_key_exists($key, $payload);
}

// ecommerce_product.status lưu chuỗi 'true'/'false' (đôi khi '1'/'0'); chuẩn hoá điều kiện "đang bán".
function order_product_active_sql(string $alias = 'p'): string {
	$col = $alias !== '' ? "{$alias}.status" : 'status';
	return "({$col} = 'true' OR {$col} = '1' OR {$col} = 1 OR LOWER({$col}) = 'active')";
}

function normalizeDecimalInput($raw): ?string {
	if ($raw === null) return null;
	$s = trim((string)$raw);
	if ($s === '') return null;

	$s = preg_replace('/[^0-9,\.\-]/', '', $s);
	if ($s === '' || $s === '-' || $s === ',' || $s === '.') return null;

	$dotCount = substr_count($s, '.');
	$commaCount = substr_count($s, ',');

	if ($dotCount > 0 && $commaCount > 0) {
		$lastDot = strrpos($s, '.');
		$lastComma = strrpos($s, ',');
		$decSep = ($lastComma > $lastDot) ? ',' : '.';
		$thouSep = ($decSep === ',') ? '.' : ',';
		$s = str_replace($thouSep, '', $s);
		if ($decSep === ',') {
			$s = str_replace(',', '.', $s);
		}
	} elseif ($commaCount > 0) {
		if ($commaCount === 1 && preg_match('/,\d{1,2}$/', $s)) {
			$s = str_replace(',', '.', $s);
		} else {
			$s = str_replace(',', '', $s);
		}
	} elseif ($dotCount > 0) {
		if (!($dotCount === 1 && preg_match('/\.\d{1,2}$/', $s))) {
			$s = str_replace('.', '', $s);
		}
	}

	if (!is_numeric($s)) return null;
	return (string)round((float)$s);
}


function normalizeStatusKey(string $status): string {
	$info = ecommerce_order_status_info($status);
	return (string)($info['key'] ?? 'pending');
}


function deleteOrderRelatedLogs(mysqli $ithanhloc, string $orderId): bool {
	$orderId = trim($orderId);
	if ($orderId === '') return false;

	// Delete user_logs (meta_json contains order_id)
	if (tableExists($ithanhloc, 'user_logs')) {
		$needle = '%"order_id":"' . $ithanhloc->real_escape_string($orderId) . '"%';
		$stmt = $ithanhloc->prepare('DELETE FROM user_logs WHERE meta_json LIKE ?');
		if ($stmt) {
			$stmt->bind_param('s', $needle);
			$stmt->execute();
			$stmt->close();
		}
	}

	// Delete user_notification (type=order, meta_json contains order_id OR link contains order_id)
	if (tableExists($ithanhloc, 'user_notification')) {
		$ids = [];
		$needleMeta = '%"order_id":"' . $ithanhloc->real_escape_string($orderId) . '"%';
		$needleLink = '%order_id=' . $ithanhloc->real_escape_string($orderId) . '%';
		$stmtSel = $ithanhloc->prepare("SELECT id FROM user_notification WHERE LOWER(TRIM(type))='order' AND (meta_json LIKE ? OR link LIKE ?)");
		if ($stmtSel) {
			$stmtSel->bind_param('ss', $needleMeta, $needleLink);
			$stmtSel->execute();
			$res = $stmtSel->get_result();
			if ($res instanceof mysqli_result) {
				while ($r = $res->fetch_assoc()) {
					$id = (int)($r['id'] ?? 0);
					if ($id > 0) $ids[$id] = true;
				}
			}
			$stmtSel->close();
		}

		$ids = array_keys($ids);
		if ($ids) {
			$ph = implode(',', array_fill(0, count($ids), '?'));
			$types = str_repeat('i', count($ids));
			$params = $ids;

			// Child tables
			foreach (['user_notification_read', 'user_notification_like', 'user_notification_comment'] as $child) {
				if (!tableExists($ithanhloc, $child)) continue;
				$stmtChild = $ithanhloc->prepare("DELETE FROM {$child} WHERE notification_id IN ({$ph})");
				if ($stmtChild) {
					bindParamsDynamic($stmtChild, $types, $params);
					$stmtChild->execute();
					$stmtChild->close();
				}
			}
		}

		// Finally delete the notification rows themselves
		$stmtDel = $ithanhloc->prepare("DELETE FROM user_notification WHERE LOWER(TRIM(type))='order' AND (meta_json LIKE ? OR link LIKE ?)");
		if ($stmtDel) {
			$stmtDel->bind_param('ss', $needleMeta, $needleLink);
			$stmtDel->execute();
			$stmtDel->close();
		}
	}

	return true;
}

function deleteOrderCascadeAdmin(mysqli $ithanhloc, string $orderId): array {
	$orderId = trim($orderId);
	if ($orderId === '') return ['ok' => false, 'msg' => 'Thiếu mã đơn'];

	try {
		$ithanhloc->begin_transaction();

		// Related logs first (notifications, user logs)
		deleteOrderRelatedLogs($ithanhloc, $orderId);

		// ZNS logs
		if (tableExists($ithanhloc, 'zns_notification')) {
			$st = $ithanhloc->prepare('DELETE FROM zns_notification WHERE CAST(order_id AS CHAR) = ?');
			if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
		}

		// GHN records (shipping integration)
		if (tableExists($ithanhloc, 'ghn_order')) {
			$ghnIds = [];
			$ghnCodes = [];
			$stSel = $ithanhloc->prepare('SELECT id, order_code FROM ghn_order WHERE system_order_id=?');
			if ($stSel) {
				$stSel->bind_param('s', $orderId);
				$stSel->execute();
				$res = $stSel->get_result();
				if ($res instanceof mysqli_result) {
					while ($r = $res->fetch_assoc()) {
						$gid = (int)($r['id'] ?? 0);
						if ($gid > 0) $ghnIds[$gid] = true;
						$code = trim((string)($r['order_code'] ?? ''));
						if ($code !== '') $ghnCodes[$code] = true;
					}
				}
				$stSel->close();
			}

			$ghnIds = array_keys($ghnIds);
			$ghnCodes = array_keys($ghnCodes);

			// Delete items by ghn_order_id
			if ($ghnIds && tableExists($ithanhloc, 'ghn_order_item')) {
				$ph = implode(',', array_fill(0, count($ghnIds), '?'));
				$types = str_repeat('i', count($ghnIds));
				$params = $ghnIds;
				$st = $ithanhloc->prepare("DELETE FROM ghn_order_item WHERE ghn_order_id IN ({$ph})");
				if ($st) { bindParamsDynamic($st, $types, $params); $st->execute(); $st->close(); }
			}

			// Delete logs by order_code OR ghn_order_id (support both schemas)
			if (tableExists($ithanhloc, 'ghn_order_log')) {
				$cols = array_flip(listColumns($ithanhloc, 'ghn_order_log'));
				if ($ghnCodes) {
					$ph = implode(',', array_fill(0, count($ghnCodes), '?'));
					$types = str_repeat('s', count($ghnCodes));
					$params = $ghnCodes;
					$st = $ithanhloc->prepare("DELETE FROM ghn_order_log WHERE order_code IN ({$ph})");
					if ($st) { bindParamsDynamic($st, $types, $params); $st->execute(); $st->close(); }
				}
				if ($ghnIds && app_table_has_col($cols, 'ghn_order_id')) {
					$ph = implode(',', array_fill(0, count($ghnIds), '?'));
					$types = str_repeat('i', count($ghnIds));
					$params = $ghnIds;
					$st = $ithanhloc->prepare("DELETE FROM ghn_order_log WHERE ghn_order_id IN ({$ph})");
					if ($st) { bindParamsDynamic($st, $types, $params); $st->execute(); $st->close(); }
				}
			}

			// Finally delete ghn_order rows
			$stDel = $ithanhloc->prepare('DELETE FROM ghn_order WHERE system_order_id=?');
			if ($stDel) { $stDel->bind_param('s', $orderId); $stDel->execute(); $stDel->close(); }
		}

		// Optional related tables directly keyed by order_id
		if (tableExists($ithanhloc, 'vnpay_ipn_log')) {
			$st = $ithanhloc->prepare('DELETE FROM vnpay_ipn_log WHERE order_id=?');
			if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
		}
		if (tableExists($ithanhloc, 'ecommerce_order_review')) {
			$st = $ithanhloc->prepare('DELETE FROM ecommerce_order_review WHERE order_id=?');
			if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
		}
		if (tableExists($ithanhloc, 'ecommerce_order_invoice')) {
			$st = $ithanhloc->prepare('DELETE FROM ecommerce_order_invoice WHERE order_id=?');
			if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
		}

		// Các bảng phụ khác keyed bằng order_id (log trạng thái, trả hàng, refund)
		foreach (['ecommerce_order_log', 'ecommerce_order_return', 'ecommerce_order_refund', 'ecommerce_refund_tx'] as $tbl) {
			if (tableExists($ithanhloc, $tbl)) {
				$st = $ithanhloc->prepare("DELETE FROM {$tbl} WHERE order_id=?");
				if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
			}
		}

		// Log ví xu (cột ref_order_id) — xoá lịch sử giao dịch xu gắn với đơn
		if (tableExists($ithanhloc, 'user_balance_log')) {
			$st = $ithanhloc->prepare('DELETE FROM user_balance_log WHERE ref_order_id=?');
			if ($st) { $st->bind_param('s', $orderId); $st->execute(); $st->close(); }
		}

		// Then delete the order itself
		$stmt = $ithanhloc->prepare('DELETE FROM ecommerce_order WHERE order_id=?');
		if (!$stmt) {
			$ithanhloc->rollback();
			return ['ok' => false, 'msg' => 'Không thể xóa đơn'];
		}
		$stmt->bind_param('s', $orderId);
		$ok = $stmt->execute();
		$err = $stmt->error;
		$aff = $stmt->affected_rows;
		$stmt->close();
		if (!$ok) {
			$ithanhloc->rollback();
			return ['ok' => false, 'msg' => $err ?: 'Không thể xóa đơn'];
		}

		$ithanhloc->commit();
		return ['ok' => true, 'deleted' => $aff > 0 ? 1 : 0];
	} catch (Throwable $e) {
		try { $ithanhloc->rollback(); } catch (Throwable $e2) {}
		return ['ok' => false, 'msg' => $e->getMessage()];
	}
}



function buildAdminTimeline(mysqli $ithanhloc, array $order): array {
	$orderId = (string)($order['order_id'] ?? '');
	if ($orderId === '') return [];

	$statusLabels = [
		'pending'          => 'Chờ xác nhận',
		'processing'       => 'Đã xác nhận — Đang chuẩn bị hàng',
		'shipping'         => 'Đang giao hàng',
		'delivered'        => 'Đã giao hàng thành công',
		'cancel_requested' => 'Yêu cầu hủy đơn — Chờ xét duyệt',
		'canceled'         => 'Đơn hàng đã hủy',
		'return_requested' => 'Yêu cầu trả hàng — Chờ xét duyệt',
		'returned'         => 'Đã hoàn trả hàng',
		'refunded'         => 'Đã hoàn tiền',
	];
	$actorLabels = [
		'admin'    => 'Admin',
		'customer' => 'Khách hàng',
		'system'   => 'Hệ thống',
		'carrier'  => 'Đơn vị vận chuyển',
	];

	// Đọc từ ecommerce_order_log — nguồn sự thật duy nhất
	$logs = ecommerce_order_log_fetch($ithanhloc, $orderId);

	$timeline = [];

	// Bước đầu luôn là "Tạo đơn"
	if (!empty($order['created_at'])) {
		$timeline[] = [
			'time'       => (string)$order['created_at'],
			'time_human' => date('H:i d/m/Y', strtotime((string)$order['created_at'])),
			'label'      => 'Tạo đơn hàng',
			'status'     => 'created',
			'actor'      => 'Khách hàng',
			'note'       => '',
			'source'     => 'system',
		];
	}

	// Các event nội bộ không hiển thị trên timeline (cả admin & user)
	$hiddenEvents = ['stock_restored'];
	if (!empty($logs)) {
		foreach ($logs as $log) {
			$event      = (string)($log['event'] ?? '');
			if (in_array($event, $hiddenEvents, true)) continue;
			$statusTo   = (string)($log['status_to'] ?? '');
			$statusFrom = (string)($log['status_from'] ?? '');
			$isStatusChange = ($event === 'status_changed') || ($statusTo !== '' && $statusTo !== $statusFrom);

			$eventLabels = [
				'cancel_approved'    => 'Duyệt yêu cầu hủy',
				'cancel_rejected'    => 'Từ chối yêu cầu hủy',
				'return_approved'    => 'Đã duyệt yêu cầu trả hàng',
				'return_rejected'    => 'Từ chối yêu cầu trả hàng',
				'return_received'    => 'Đã nhận lại hàng từ khách',
				'return_inspected'   => 'Đã kiểm tra hàng hoàn',
				'shipping_updated'   => 'Cập nhật vận chuyển',
				'carrier_updated'    => 'Cập nhật từ đơn vị vận chuyển',
				'payment_updated'    => 'Cập nhật thanh toán',
				'address_updated'    => 'Cập nhật địa chỉ nhận hàng',
			];

			// Ưu tiên: nhãn sự kiện cụ thể → nhãn trạng thái → fallback
			if ($event !== '' && isset($eventLabels[$event])) {
				$label = $eventLabels[$event];
			} elseif ($isStatusChange && isset($statusLabels[$statusTo])) {
				$label = $statusLabels[$statusTo];
			} elseif ($statusTo !== '' && isset($statusLabels[$statusTo])) {
				$label = $statusLabels[$statusTo];
			} else {
				$label = 'Cập nhật đơn hàng';
			}

			$actorType = (string)($log['actor_type'] ?? 'system');
			$note      = (string)($log['note'] ?? '');
			$note = preg_replace('/^(Admin|Khách|Khách hàng)\s+(đổi địa chỉ:\s*)/iu', '', $note);
			$note = preg_replace('/^(Admin|Khách|Khách hàng)\s+(duyệt hủy|từ chối hủy|duyệt yêu cầu hủy|từ chối yêu cầu hủy|duyệt yêu cầu trả hàng|từ chối yêu cầu trả hàng|xác nhận đã hoàn tiền)/iu', '$2', $note);
			if ($note !== '') {
				$note = mb_strtoupper(mb_substr($note, 0, 1)) . mb_substr($note, 1);
			}
			$time      = (string)($log['created_at'] ?? '');
			$timeline[] = [
				'time'       => $time,
				'time_human' => $time !== '' ? date('H:i d/m/Y', strtotime($time)) : '',
				'label'      => $label,
				'status'     => $statusTo,
				'event'      => $event,
				'actor'      => $actorLabels[$actorType] ?? $actorType,
				'note'       => $note,
				'source'     => $actorType === 'carrier' ? 'ghn' : 'admin',
			];
		}
	} else {
		// Fallback: đơn cũ chưa có log → đọc từ timestamp columns
		$push = static function(array &$arr, string $time, string $label, string $status): void {
			$time = trim($time);
			if ($time === '') return;
			$arr[] = [
				'time'       => $time,
				'time_human' => date('H:i d/m/Y', strtotime($time)),
				'label'      => $label,
				'status'     => $status,
				'actor'      => 'Hệ thống',
				'note'       => '',
				'source'     => 'admin',
			];
		};
		$shippingLabel = 'Bàn giao vận chuyển';
		if (!empty($order['shipping_carrier'])) $shippingLabel .= ' (' . $order['shipping_carrier'] . ')';
		if (!empty($order['shipping_tracking'])) $shippingLabel .= ' - Mã: ' . $order['shipping_tracking'];
		$push($timeline, (string)($order['shipped_at'] ?? ''), $shippingLabel, 'shipping');
		$push($timeline, (string)($order['delivered_at'] ?? ''), 'Đã giao hàng', 'delivered');
		$push($timeline, (string)($order['return_requested_at'] ?? ''), 'Yêu cầu trả hàng', 'return_requested');
		$push($timeline, (string)($order['return_resolved_at'] ?? ''), 'Hoàn tất trả hàng', 'returned');
		$push($timeline, (string)($order['refunded_at'] ?? ''), 'Đã hoàn tiền', 'refunded');
		$push($timeline, (string)($order['canceled_at'] ?? ''), 'Đã hủy', 'canceled');
	}

	usort($timeline, static function($a, $b){
		return strtotime((string)($a['time'] ?? '')) <=> strtotime((string)($b['time'] ?? ''));
	});

	return $timeline;
}

function fetchOrderShippingLive(mysqli $ithanhloc, array $order): array {
	$result = [
		'carrier' => trim((string)($order['shipping_carrier'] ?? '')),
		'tracking' => trim((string)($order['shipping_tracking'] ?? '')),
		'service' => trim((string)($order['shipping_method_label'] ?? $order['shipping_method'] ?? '')),
		'eta' => trim((string)($order['shipping_eta'] ?? '')),
		'status' => '',
		'status_text' => '',
		'updated_at' => '',
		'source' => 'order',
		'carrier_timeline' => [],   // kept for backward compat, unused internally
		'admin_timeline'   => [],   // kept for backward compat, unused internally
		'timeline'         => [],   // nguồn sự thật duy nhất, gộp order_log + GHN
	];

	$orderId = trim((string)($order['order_id'] ?? ''));
	if ($orderId === '') return $result;

	// Always ensure table exists so buildAdminTimeline() can read logs
	ecommerce_order_log_ensure_table($ithanhloc);

	if (!tableExists($ithanhloc, 'ghn_order')) {
		$result['timeline'] = buildAdminTimeline($ithanhloc, $order);
		return $result;
	}

	$stmt = $ithanhloc->prepare('SELECT * FROM ghn_order WHERE system_order_id=? ORDER BY id DESC LIMIT 1');
	if (!$stmt) {
		$result['timeline'] = buildAdminTimeline($ithanhloc, $order);
		return $result;
	}
	$stmt->bind_param('s', $orderId);
	$stmt->execute();
	$ghn = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$ghn) {
		$result['timeline'] = buildAdminTimeline($ithanhloc, $order);
		return $result;
	}

	$orderCode = trim((string)($ghn['order_code'] ?? ''));
	$status = trim((string)($ghn['status'] ?? ''));
	$statusText = trim((string)($ghn['status_text'] ?? ''));
	$updatedAt = trim((string)($ghn['updated_at'] ?? $ghn['created_at'] ?? ''));

	$service = '';
	$serviceCandidates = [
		'service_name', 'service_type', 'service_type_name', 'service'
	];
	foreach ($serviceCandidates as $key) {
		$raw = trim((string)($ghn[$key] ?? ''));
		if ($raw !== '') { $service = $raw; break; }
	}
	if ($service === '') {
		$service_type_id = intval($ghn['service_type_id'] ?? 0);
		if ($service_type_id === 1) {
			$service = 'Nhanh';
		} elseif ($service_type_id === 2) {
			$service = 'Chuẩn';
		} elseif ($service_type_id === 3) {
			$service = 'Tiết kiệm';
		} elseif ($service_type_id === 5) {
			$service = 'Traditional Delivery';
		} else {
			$service_id = intval($ghn['service_id'] ?? 0);
			if ($service_id > 0) {
				$service = 'Service #' . $service_id;
			} elseif ($service_type_id > 0) {
				$service = 'Service type #' . $service_type_id;
			}
		}
	}

	$eta = '';
	$etaCandidates = [
		'expected_delivery_time', 'leadtime', 'lead_time', 'deliver_date', 'deliver_time'
	];
	foreach ($etaCandidates as $key) {
		$raw = trim((string)($ghn[$key] ?? ''));
		if ($raw !== '') { $eta = $raw; break; }
	}

	$result['carrier'] = 'GHN';
	$result['tracking'] = $orderCode !== '' ? $orderCode : $result['tracking'];
	$result['service'] = $service !== '' ? $service : $result['service'];
	$result['eta'] = $eta !== '' ? $eta : $result['eta'];
	$result['status'] = $status;
	$result['status_text'] = ghnStatusLabel($status, $statusText);
	$result['updated_at'] = $updatedAt;
	$result['source'] = 'ghn_order';

	// Đọc GHN carrier logs và ghi vào ecommerce_order_log (actor_type=carrier) nếu chưa có
	if ($orderCode !== '' && tableExists($ithanhloc, 'ghn_order_log')) {
		$stmtLog = $ithanhloc->prepare('SELECT status, status_text, created_at FROM ghn_order_log WHERE order_code=? ORDER BY id ASC LIMIT 100');
		if ($stmtLog) {
			$stmtLog->bind_param('s', $orderCode);
			$stmtLog->execute();
			$resLog = $stmtLog->get_result();
			while ($row = $resLog->fetch_assoc()) {
				$logStatus = trim((string)($row['status'] ?? ''));
				$logText   = trim((string)($row['status_text'] ?? ''));
				$time      = trim((string)($row['created_at'] ?? ''));
				$label     = ghnStatusLabel($logStatus, $logText);
				// Sync carrier log: nếu đã có row với status_to này → update created_at + note
				// để đảm bảo dùng thời gian gốc từ GHN (không phải thời điểm admin view)
				if ($time !== '') {
					$ts = date('Y-m-d H:i:s', strtotime($time));
					$stmtCheck = $ithanhloc->prepare(
						'SELECT id FROM ecommerce_order_log WHERE order_id=? AND actor_type="carrier" AND status_to=? LIMIT 1'
					);
					if ($stmtCheck) {
						$stmtCheck->bind_param('ss', $orderId, $logStatus);
						$stmtCheck->execute();
						$existRow = $stmtCheck->get_result()->fetch_assoc();
						$stmtCheck->close();
						if ($existRow) {
							// Update để đảm bảo created_at + note khớp dữ liệu GHN
							$stmtUpd = $ithanhloc->prepare(
								'UPDATE ecommerce_order_log SET created_at=?, note=? WHERE id=?'
							);
							if ($stmtUpd) {
								$existId = (int)$existRow['id'];
								$stmtUpd->bind_param('ssi', $ts, $label, $existId);
								$stmtUpd->execute();
								$stmtUpd->close();
							}
						} else {
							ecommerce_order_log_insert($ithanhloc, $orderId, 'carrier', 0, 'carrier_updated', '', $logStatus, $label, $time);
						}
					}
				}
			}
			$stmtLog->close();
		}
	}

	// Gộp toàn bộ timeline từ ecommerce_order_log (đã bao gồm admin + customer + carrier)
	$result['timeline'] = buildAdminTimeline($ithanhloc, $order);

	return $result;
}

function loadReviewsByOrderIds(mysqli $ithanhloc, array $orderIds): array {
	$orderIds = array_values(array_unique(array_filter(array_map('trim', $orderIds), fn($v) => $v !== '')));
	if (!$orderIds) return [];
	$safe = implode(',', array_map(fn($id) => "'" . $ithanhloc->real_escape_string($id) . "'", $orderIds));
	$res = $ithanhloc->query("SELECT order_id, rating, comment, created_at FROM ecommerce_order_review WHERE order_id IN ($safe)");
	$map = [];
	if ($res) {
		while ($r = $res->fetch_assoc()) {
			$oid = (string)($r['order_id'] ?? '');
			if ($oid === '') continue;
			$map[$oid] = [
				'rating' => (int)($r['rating'] ?? 0),
				'comment' => $r['comment'] ?? '',
				'created_at' => $r['created_at'] ?? ''
			];
		}
	}
	return $map;
}

function applyStatusTransition(mysqli $ithanhloc, string $orderId, string $newStatus, ?string $carrier, ?string $tracking): array {
	$newStatus = normalizeStatusKey($newStatus);
	$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
	$currentStatus = '';
	$orderUserId = 0;
	$currentCarrier = '';
	$currentTracking = '';
	$stmtCur = $ithanhloc->prepare('SELECT status, user_id, shipping_carrier, shipping_tracking FROM ecommerce_order WHERE order_id=? LIMIT 1');
	if ($stmtCur) {
		$stmtCur->bind_param('s', $orderId);
		$stmtCur->execute();
		$rowCur = $stmtCur->get_result()->fetch_assoc();
		$stmtCur->close();
		if ($rowCur) {
			$currentStatus = (string)($rowCur['status'] ?? '');
			$orderUserId = (int)($rowCur['user_id'] ?? 0);
			$currentCarrier = (string)($rowCur['shipping_carrier'] ?? '');
			$currentTracking = (string)($rowCur['shipping_tracking'] ?? '');
		}
	}

	$set = ['status=?'];
	$params = [$newStatus];
	$types = 's';

	if (isset($cols['updated_at'])) { $set[] = 'updated_at=NOW()'; }
	if (isset($cols['shipping_carrier']) && $carrier !== null) { $set[] = 'shipping_carrier=?'; $params[] = $carrier; $types .= 's'; }
	if (isset($cols['shipping_tracking']) && $tracking !== null) { $set[] = 'shipping_tracking=?'; $params[] = $tracking; $types .= 's'; }

	// Support canceled_reason if provided
	if (isset($cols['canceled_reason']) && isset($_REQUEST['canceled_reason'])) {
		$set[] = 'canceled_reason=?';
		$params[] = (string)$_REQUEST['canceled_reason'];
		$types .= 's';
	}

	if ($newStatus === 'shipping' && isset($cols['shipped_at'])) { $set[] = 'shipped_at=COALESCE(shipped_at, NOW())'; }
	if ($newStatus === 'delivered' && isset($cols['delivered_at'])) { $set[] = 'delivered_at=COALESCE(delivered_at, NOW())'; }
	if ($newStatus === 'canceled' && isset($cols['canceled_at'])) { $set[] = 'canceled_at=COALESCE(canceled_at, NOW())'; }
	if ($newStatus === 'returned' && isset($cols['return_resolved_at'])) { $set[] = 'return_resolved_at=COALESCE(return_resolved_at, NOW())'; }
	if ($newStatus === 'refunded' && isset($cols['refunded_at'])) { $set[] = 'refunded_at=COALESCE(refunded_at, NOW())'; }

	$params[] = $orderId;
	$types .= 's';
	$sql = 'UPDATE ecommerce_order SET ' . implode(',', $set) . ' WHERE order_id=?';
	$stmt = $ithanhloc->prepare($sql);
	if (!$stmt) return ['ok' => false, 'msg' => $ithanhloc->error];
	if (!bindParamsDynamic($stmt, $types, $params)) { $stmt->close(); return ['ok' => false, 'msg' => 'Bind failed']; }
	$ok = $stmt->execute();
	$err = $stmt->error;
	$stmt->close();
	if ($ok) {
		syncXuByOrderStatus($ithanhloc, $orderId, $newStatus);

		// Hoàn kho khi đơn chuyển sang trạng thái huỷ / đã trả / đã hoàn tiền.
		// Chỉ hoàn khi trạng thái trước đó CHƯA phải trạng thái terminal cùng nhóm (tránh hoàn 2 lần
		// dù restoreStockForOrder đã có idempotent guard riêng).
		$restoreStatuses = ['canceled', 'returned', 'refunded'];
		if (in_array($newStatus, $restoreStatuses, true) && !in_array($currentStatus, $restoreStatuses, true)) {
			$stmtO = $ithanhloc->prepare('SELECT order_id, status, products_json, voucher_code, voucher_shipping_code, voucher_payment_code, payment_status, payment_method, payment_gateway, total_amount FROM ecommerce_order WHERE order_id=? LIMIT 1');
			if ($stmtO) {
				$stmtO->bind_param('s', $orderId);
				$stmtO->execute();
				$orderRow = $stmtO->get_result()->fetch_assoc();
				$stmtO->close();
				if ($orderRow) {
					global $__sessionUserId;
					$restActorId = (int)($__sessionUserId ?? 0);
					if (function_exists('restoreStockForOrder')) {
						try { restoreStockForOrder($ithanhloc, $orderRow, 'admin', $restActorId); }
						catch (Throwable $e) { error_log('restoreStockForOrder (admin transition) failed: ' . $e->getMessage()); }
					}
					if (function_exists('restoreVoucherUsageForOrder')) {
						try { restoreVoucherUsageForOrder($ithanhloc, $orderRow, 'admin', $restActorId); }
						catch (Throwable $e) { error_log('restoreVoucherUsageForOrder failed: ' . $e->getMessage()); }
					}
					if (function_exists('markRefundPendingForOrder')) {
						try { markRefundPendingForOrder($ithanhloc, $orderRow, 'admin', $restActorId); }
						catch (Throwable $e) { error_log('markRefundPendingForOrder failed: ' . $e->getMessage()); }
					}
				}
			}
		}

		$statusChanged = ($currentStatus !== $newStatus);
		$carrierChanged = ($carrier !== null && $carrier !== $currentCarrier);
		$trackingChanged = ($tracking !== null && $tracking !== $currentTracking);

		if ($statusChanged || $carrierChanged || $trackingChanged || $newStatus === 'shipping') {
			$labelInfo = ecommerce_order_status_info($newStatus);
			$label = (string)($labelInfo['label'] ?? $newStatus);
            
            $logMsg = $statusChanged ? ('Cập nhật trạng thái: ' . $label) : 'Cập nhật thông tin vận chuyển';
            $finalCarrier = trim($carrier ?? $currentCarrier);
            if ($finalCarrier !== '') {
                $logMsg .= ' (' . $finalCarrier . ')';
            }
            $finalTracking = trim($tracking ?? $currentTracking);
            if ($finalTracking !== '') {
                $logMsg .= ' - Mã: ' . $finalTracking;
            }

            // Log hành động cho admin/hệ thống (Sử dụng session admin nếu có)
            global $__sessionUserId;
            $actorId = (int)($__sessionUserId ?? 0);
			app_user_log($ithanhloc, $actorId, 'order_status', $logMsg, [
				'order_id' => $orderId,
				'status' => $newStatus,
                'carrier' => $carrier,
                'tracking' => $tracking
			]);
            // Ghi vào ecommerce_order_log (nguồn sự thật cho timeline realtime)
            ecommerce_order_log_insert(
                $ithanhloc, $orderId, 'admin', $actorId,
                $statusChanged ? 'status_changed' : 'shipping_updated',
                $currentStatus, $newStatus, $logMsg
            );

            // Thông báo cho khách hàng
            if ($orderUserId > 0) {
                $link = '/view-order?order_id=' . urlencode($orderId);
                $now = date('Y-m-d H:i:s');
                $tplCode = ($newStatus === 'canceled') ? 'order_canceled' : 'order_status_updated';
                $vars = [
                    'order_id' => $orderId,
                    'status' => $newStatus,
                    'status_label' => $label,
                    'carrier' => $carrier,
                    'tracking' => $tracking,
                    'time' => $now,
                    'link' => $link,
                    'event' => $tplCode,
                ];
                app_user_notify_template($ithanhloc, $orderUserId, $tplCode, $vars);
            }
		}
	}
	return ['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật trạng thái' : $err];
}

// DataTables source
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
	$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
	$draw = intval($_GET['draw'] ?? 1);
	$start = intval($_GET['start'] ?? 0);
	$length = intval($_GET['length'] ?? 10);
	$searchVal = trim($_GET['search']['value'] ?? '');

	$columns = [
		0 => 'order_id',
		1 => 'order_id',
		2 => 'order_id',
		3 => 'user_name',
		4 => 'created_at',
		5 => 'status',
		6 => isset($cols['products_json']) ? 'products_json' : (isset($cols['product']) ? 'product' : 'order_id'),
		7 => isset($cols['phone']) ? 'phone' : 'order_id',
		8 => 'order_id',
		9 => 'order_id'
	];
	$colIdx = intval($_GET['order'][0]['column'] ?? 4);
	$orderCol = $columns[$colIdx] ?? 'created_at';
	$orderDir = (($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

	$where = [];
	$params = [];
	$types = '';

	if ($searchVal !== '') {
		$like = "%{$searchVal}%";
		$orParts = [
			"order_id LIKE ?",
			"user_name LIKE ?"
		];
		$params[] = $like; $params[] = $like;
		$types .= 'ss';

		if (isset($cols['phone'])) { $orParts[] = "phone LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['email'])) { $orParts[] = "email LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['product'])) { $orParts[] = "product LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['products_json'])) { $orParts[] = "products_json LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['note'])) { $orParts[] = "note LIKE ?"; $params[] = $like; $types .= 's'; }

		$where[] = '(' . implode(' OR ', $orParts) . ')';
	}

	$startDate = $_GET['startDate'] ?? '';
	$endDate = $_GET['endDate'] ?? '';
	if ($startDate) {
		$where[] = "DATE(created_at) >= ?";
		$params[] = $startDate;
		$types .= 's';
	}
	if ($endDate) {
		$where[] = "DATE(created_at) <= ?";
		$params[] = $endDate;
		$types .= 's';
	}

	$statusFilter = $_GET['status_filter'] ?? 'all';
	if ($statusFilter !== 'all') {
		$where[] = "status = ?";
		$params[] = $statusFilter;
		$types .= 's';
	}

	$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

	$totalRes = $ithanhloc->query('SELECT COUNT(*) AS c FROM ecommerce_order');
	$total = intval(($totalRes && $totalRes->num_rows) ? $totalRes->fetch_assoc()['c'] : 0);

	if ($whereSQL) {
		$stmt = $ithanhloc->prepare("SELECT COUNT(*) AS c FROM ecommerce_order $whereSQL");
		if ($types) {
			bindParamsDynamic($stmt, $types, $params);
		}
		$stmt->execute();
		$filtered = intval($stmt->get_result()->fetch_assoc()['c'] ?? 0);
		$stmt->close();
	} else {
		$filtered = $total;
	}

	$sql = "SELECT * FROM ecommerce_order $whereSQL ORDER BY $orderCol $orderDir LIMIT ?, ?";
	$stmt = $ithanhloc->prepare($sql);
	$paramsWithLimit = $params;
	$typesWithLimit = $types . 'ii';
	$paramsWithLimit[] = $start;
	$paramsWithLimit[] = $length;
	if ($typesWithLimit) {
		bindParamsDynamic($stmt, $typesWithLimit, $paramsWithLimit);
	}
	$stmt->execute();
	$res = $stmt->get_result();

	$statusOptions = [
		'pending',
		'processing',
		'shipping',
		'delivered',
		'return_requested',
		'returned',
		'refunded',
		'canceled',
	];
	// Build map key => label từ helper chung
	$statusOptionsMap = [];
	foreach ($statusOptions as $stKey) {
		$info = ecommerce_order_status_info($stKey);
		$key = (string)($info['key'] ?? $stKey);
		$lbl = (string)($info['label'] ?? $stKey);
		$statusOptionsMap[$key] = $lbl;
	}

	$data = [];
	while ($r = $res->fetch_assoc()) {
		$statusKey = normalizeStatusKey((string)($r['status'] ?? 'pending'));
		$select = "<select class='form-select form-select-sm status-select $statusKey' onchange=\"updateStatus('" . h($r['order_id']) . "', this.value)\">";
		foreach ($statusOptionsMap as $key => $label) {
			$selected = ($key === $statusKey) ? 'selected' : '';
			$select .= "<option value='$key' $selected>$label</option>";
		}
		$select .= '</select>';

		$r['status_html'] = $select;
		$r['status_label'] = $statusOptionsMap[$statusKey] ?? 'Không xác định';
		$r['cart_html'] = buildCartHtmlFromRow($r);
		$r['total_amount_fmt'] = fmtMoney($r['total_amount'] ?? 0);
		$r['created_fmt'] = !empty($r['created_at']) ? date('H:i d/m/Y', strtotime($r['created_at'])) : '';
		$data[] = $r;
	}

	jOut([
		'draw' => $draw,
		'recordsTotal' => $total,
		'recordsFiltered' => $filtered,
		'data' => $data
	]);
}

// Shopee-like list for admin
if (isset($_GET['ajax']) && $_GET['ajax'] === 'orders_list') {
	$tab = trim((string)($_GET['tab'] ?? 'pending'));
	$q = trim((string)($_GET['q'] ?? ''));
	$startDate = trim((string)($_GET['startDate'] ?? ''));
	$endDate = trim((string)($_GET['endDate'] ?? ''));
	$page = max(1, (int)($_GET['page'] ?? 1));
	$limit = min(20, max(5, (int)($_GET['limit'] ?? 10)));
	$offset = ($page - 1) * $limit;

	$where = [];
	$params = [];
	$types = '';

	// Điều kiện theo tab cho danh sách (panel trung tâm)
	if ($tab === 'review') {
		$where[] = "status IN ('delivered','completed')";
	} elseif ($tab === 'return') {
		$where[] = "status IN ('return_requested','returned')";
	} elseif ($tab === 'preorder') {
		$where[] = "has_preorder = 1";
	} elseif ($tab !== 'all') {
		$where[] = "status = ?";
		$params[] = normalizeStatusKey($tab);
		$types .= 's';
	}

	$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
	if ($q !== '') {
		$like = "%{$q}%";
		$or = ["order_id LIKE ?", "user_name LIKE ?"]; 
		$params[] = $like; $params[] = $like; $types .= 'ss';
		if (isset($cols['phone'])) { $or[] = "phone LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['email'])) { $or[] = "email LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['products_json'])) { $or[] = "products_json LIKE ?"; $params[] = $like; $types .= 's'; }
		if (isset($cols['product'])) { $or[] = "product LIKE ?"; $params[] = $like; $types .= 's'; }
		$where[] = '(' . implode(' OR ', $or) . ')';
	}
	if ($startDate !== '') { $where[] = "DATE(created_at) >= ?"; $params[] = $startDate; $types .= 's'; }
	if ($endDate !== '') { $where[] = "DATE(created_at) <= ?"; $params[] = $endDate; $types .= 's'; }

	$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
	$stmtCount = $ithanhloc->prepare("SELECT COUNT(*) AS c FROM ecommerce_order $whereSQL");
	if ($types) { bindParamsDynamic($stmtCount, $types, $params); }
	$stmtCount->execute();
	$total = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
	$stmtCount->close();

	$sql = "SELECT * FROM ecommerce_order $whereSQL ORDER BY created_at DESC LIMIT ?, ?";
	$stmt = $ithanhloc->prepare($sql);
	$params2 = $params;
	$params2[] = $offset;
	$params2[] = $limit;
	$types2 = $types . 'ii';
	bindParamsDynamic($stmt, $types2, $params2);
	$stmt->execute();
	$res = $stmt->get_result();

	$rows = [];
	$orderIds = [];
	while ($r = $res->fetch_assoc()) {
		$r['_status_key'] = normalizeStatusKey((string)($r['status'] ?? 'pending'));
		$orderIds[] = (string)($r['order_id'] ?? '');
		$rows[] = $r;
	}
	$stmt->close();

	$reviewMap = loadReviewsByOrderIds($ithanhloc, $orderIds);
	$data = [];
	foreach ($rows as $r) {
		$oid = (string)($r['order_id'] ?? '');
		$statusKey = (string)($r['_status_key'] ?? 'pending');
		$review = $reviewMap[$oid] ?? null;
		$reviewed = is_array($review);
		if ($tab === 'review' && $reviewed) continue;

		$data[] = [
			'order_id' => $oid,
			'user_id' => isset($r['user_id']) ? (int)$r['user_id'] : 0,
			'user_name' => $r['user_name'] ?? '',
			'phone' => $r['phone'] ?? '',
			'email' => $r['email'] ?? '',
			'address' => $r['address'] ?? '',
			'note' => $r['note'] ?? '',
			'product' => $r['product'] ?? '',
			'products_json' => $r['products_json'] ?? '',
			'has_preorder' => isset($r['has_preorder']) ? (int)$r['has_preorder'] : 0,
			'total_amount' => $r['total_amount'] ?? '',
			'created_at' => $r['created_at'] ?? '',
			'created_fmt' => !empty($r['created_at']) ? date('H:i d/m/Y', strtotime($r['created_at'])) : '',
			'status' => $statusKey,
			'status_label' => (string)(ecommerce_order_status_info($statusKey)['label'] ?? 'Chờ xác nhận'),
			'shipping_carrier' => $r['shipping_carrier'] ?? '',
			'shipping_tracking' => $r['shipping_tracking'] ?? '',
			'total_amount_fmt' => fmtMoney($r['total_amount'] ?? 0),
			'payment_status' => $r['payment_status'] ?? '',
			'payment_method' => $r['payment_method'] ?? '',
			'payment_gateway' => $r['payment_gateway'] ?? '',
			// Chuẩn hoá label thanh toán qua helper chung
			... (function () use ($r) {
				$info = ecommerce_payment_info($r['payment_method'] ?? '', $r['payment_status'] ?? '', $r['payment_gateway'] ?? '');
				return [
					'payment_status_label' => (string)($info['status_label'] ?? ''),
					'payment_method_label' => (string)($info['method_label'] ?? ''),
				];
			})(),
			'review' => $review,
			'cart_html' => buildCartHtmlFromRow($r),
		];
	}

	// Tính tổng số đơn theo từng trạng thái cho thanh thống kê (bỏ qua điều kiện tab, nhưng giữ bộ lọc tìm kiếm + ngày)
	$summary = [
		'all' => 0,
		'pending' => 0,
		'processing' => 0,
		'shipping' => 0,
		'delivered' => 0,
		'return_requested' => 0,
		'returned' => 0,
		'refunded' => 0,
		'canceled' => 0,
		'preorder' => 0,
	];

	$summaryWhere = [];
	$summaryParams = [];
	$summaryTypes = '';

	if ($q !== '') {
		$like = "%{$q}%";
		$or = ["order_id LIKE ?", "user_name LIKE ?"];
		$summaryParams[] = $like; $summaryParams[] = $like; $summaryTypes .= 'ss';
		if (isset($cols['phone'])) { $or[] = "phone LIKE ?"; $summaryParams[] = $like; $summaryTypes .= 's'; }
		if (isset($cols['email'])) { $or[] = "email LIKE ?"; $summaryParams[] = $like; $summaryTypes .= 's'; }
		if (isset($cols['products_json'])) { $or[] = "products_json LIKE ?"; $summaryParams[] = $like; $summaryTypes .= 's'; }
		if (isset($cols['product'])) { $or[] = "product LIKE ?"; $summaryParams[] = $like; $summaryTypes .= 's'; }
		$summaryWhere[] = '(' . implode(' OR ', $or) . ')';
	}
	if ($startDate !== '') { $summaryWhere[] = "DATE(created_at) >= ?"; $summaryParams[] = $startDate; $summaryTypes .= 's'; }
	if ($endDate !== '') { $summaryWhere[] = "DATE(created_at) <= ?"; $summaryParams[] = $endDate; $summaryTypes .= 's'; }

	$summaryWhereSQL = $summaryWhere ? ('WHERE ' . implode(' AND ', $summaryWhere)) : '';
	$sqlSummary = "SELECT status, COUNT(*) AS c FROM ecommerce_order $summaryWhereSQL GROUP BY status";
	$stmtSummary = $ithanhloc->prepare($sqlSummary);
	if ($stmtSummary) {
		if ($summaryTypes) {
			$stmtSummary->bind_param($summaryTypes, ...$summaryParams);
		}
		$stmtSummary->execute();
		$resSummary = $stmtSummary->get_result();
		while ($row = $resSummary->fetch_assoc()) {
			$key = normalizeStatusKey((string)($row['status'] ?? 'pending'));
			$count = (int)($row['c'] ?? 0);
			if (!isset($summary[$key])) $summary[$key] = 0;
			$summary[$key] += $count;
			$summary['all'] += $count;
		}
		$stmtSummary->close();
	}

	// Đếm riêng số đơn có hàng đặt trước (cùng bộ lọc tìm kiếm + ngày)
	$preWhere = $summaryWhere;
	$preWhere[] = "has_preorder = 1";
	$preWhereSQL = 'WHERE ' . implode(' AND ', $preWhere);
	$stmtPre = $ithanhloc->prepare("SELECT COUNT(*) AS c FROM ecommerce_order $preWhereSQL");
	if ($stmtPre) {
		if ($summaryTypes) { $stmtPre->bind_param($summaryTypes, ...$summaryParams); }
		$stmtPre->execute();
		$summary['preorder'] = (int)($stmtPre->get_result()->fetch_assoc()['c'] ?? 0);
		$stmtPre->close();
	}

	jOut([
		'ok' => true,
		'page' => $page,
		'limit' => $limit,
		'total' => $total,
		'data' => array_values($data),
		'summary' => $summary,
	]);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'order_detail') {
	$orderId = trim((string)($_GET['order_id'] ?? ''));
	if ($orderId === '') {
		jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
	}

	$stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
	if (!$stmt) {
		jOut(['ok' => false, 'msg' => 'Không thể tải đơn']);
	}
	$stmt->bind_param('s', $orderId);
	$stmt->execute();
	$order = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$order) {
		jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn']);
	}

	// Thông tin hoá đơn (nếu có)
	$invoice = null;
	if (tableExists($ithanhloc, 'ecommerce_order_invoice')) {
		$stmtInv = $ithanhloc->prepare('SELECT * FROM ecommerce_order_invoice WHERE order_id=? LIMIT 1');
		if ($stmtInv) {
			$stmtInv->bind_param('s', $orderId);
			$stmtInv->execute();
			$invoice = $stmtInv->get_result()->fetch_assoc() ?: null;
			$stmtInv->close();
		}
	}

	$review = null;
	$stmt = $ithanhloc->prepare('SELECT rating, comment, admin_reply, replied_at, created_at FROM ecommerce_order_review WHERE order_id=? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('s', $orderId);
		$stmt->execute();
		$review = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}

	$order['total_amount_fmt'] = fmtMoney($order['total_amount'] ?? 0);
	$payInfo = ecommerce_payment_info($order['payment_method'] ?? '', $order['payment_status'] ?? '', $order['payment_gateway'] ?? '');
	$order['payment_status_label'] = (string)($payInfo['status_label'] ?? '');
	$order['payment_method_label'] = (string)($payInfo['method_label'] ?? '');
	$order['created_fmt'] = !empty($order['created_at']) ? date('H:i d/m/Y', strtotime($order['created_at'])) : '';
	$shippingLive = fetchOrderShippingLive($ithanhloc, $order);

	$fullLogs = ecommerce_order_log_fetch($ithanhloc, $orderId);

	// Phí ship GHN thực tế (để so với phí lúc checkout)
	$ghnFee = null;
	if (tableExists($ithanhloc, 'ghn_order')) {
		$stF = $ithanhloc->prepare('SELECT shipping_fee FROM ghn_order WHERE system_order_id=? ORDER BY id DESC LIMIT 1');
		if ($stF) {
			$stF->bind_param('s', $orderId);
			$stF->execute();
			$rowF = $stF->get_result()->fetch_assoc();
			$stF->close();
			if ($rowF && $rowF['shipping_fee'] !== null) $ghnFee = (float)$rowF['shipping_fee'];
		}
	}

	jOut(['ok' => true, 'order' => $order, 'review' => $review, 'shipping_live' => $shippingLive, 'invoice' => $invoice, 'full_logs' => $fullLogs, 'ghn_fee' => $ghnFee]);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'ipn_logs') {
	$orderId = trim((string)($_GET['order_id'] ?? ''));
	if ($orderId === '') {
		jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
	}
	$stmt = $ithanhloc->prepare('SELECT id, order_id, response_code, transaction_status, amount, bank_code, bank_tran_no, gateway_tran_no, is_valid, ip, created_at
		FROM vnpay_ipn_log WHERE order_id=? ORDER BY id DESC LIMIT 20');
	if (!$stmt) {
		jOut(['ok' => false, 'msg' => 'Không thể tải log']);
	}
	$stmt->bind_param('s', $orderId);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	jOut(['ok' => true, 'data' => $rows]);
}

// Fetch product meta (thumb/name) by ids for admin order edit UI
if (isset($_GET['ajax']) && $_GET['ajax'] === 'product_meta') {
	$raw = trim((string)($_GET['ids'] ?? $_GET['product_ids'] ?? ''));
	if ($raw === '') {
		jOut(['ok' => true, 'data' => []]);
	}
	$parts = preg_split('/[^0-9]+/', $raw);
	$ids = [];
	foreach ($parts as $p) {
		$id = (int)$p;
		if ($id > 0) $ids[$id] = true;
		if (count($ids) >= 200) break;
	}
	$ids = array_keys($ids);
	if (!$ids) {
		jOut(['ok' => true, 'data' => []]);
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$sql = "SELECT id, product_name, image_url FROM ecommerce_product WHERE id IN ($placeholders)";
	$stmt = $ithanhloc->prepare($sql);
	if (!$stmt) {
		jOut(['ok' => false, 'msg' => 'Không thể tải sản phẩm']);
	}
	$types = str_repeat('i', count($ids));
	$params = $ids;
	if (!bindParamsDynamic($stmt, $types, $params)) {
		$stmt->close();
		jOut(['ok' => false, 'msg' => 'Không thể bind tham số']);
	}
	$stmt->execute();
	$res = $stmt->get_result();
	$map = [];
	if ($res instanceof mysqli_result) {
		while ($r = $res->fetch_assoc()) {
			$pid = (string)($r['id'] ?? '');
			if ($pid === '') continue;
			$map[$pid] = [
				'product_name' => (string)($r['product_name'] ?? ''),
				'image_url' => (string)($r['image_url'] ?? ''),
				'variants' => [],
			];
		}
	}
	$stmt->close();

	// Enrich with variant images (variant_name → image_url) so UI can match by variant label
	if ($map && tableExists($ithanhloc, 'ecommerce_product_variants')) {
		$vStmt = $ithanhloc->prepare("SELECT product_id, id AS variant_id, variant_name, image_url FROM ecommerce_product_variants WHERE product_id IN ($placeholders) ORDER BY id ASC");
		if ($vStmt) {
			$vParams = $ids;
			if (bindParamsDynamic($vStmt, $types, $vParams)) {
				$vStmt->execute();
				$vRes = $vStmt->get_result();
				if ($vRes instanceof mysqli_result) {
					while ($vr = $vRes->fetch_assoc()) {
						$vpid = (string)($vr['product_id'] ?? '');
						if ($vpid !== '' && isset($map[$vpid])) {
							$map[$vpid]['variants'][] = [
								'variant_id' => (int)($vr['variant_id'] ?? 0),
								'variant_name' => (string)($vr['variant_name'] ?? ''),
								'image_url' => (string)($vr['image_url'] ?? ''),
							];
						}
					}
				}
			}
			$vStmt->close();
		}
	}

	jOut(['ok' => true, 'data' => $map]);
}

// Tìm kiếm sản phẩm + variant để chỉnh sửa đơn hàng
if (isset($_GET['ajax']) && $_GET['ajax'] === 'product_search') {
	$q = trim((string)($_GET['q'] ?? ''));
	if (strlen($q) < 1) jOut(['ok' => true, 'rows' => []]);
	$like = '%' . str_replace(['%','_','\\'], ['\\%','\\_','\\\\'], $q) . '%';
	// Lưu ý: cột ecommerce_product.status lưu chuỗi 'true'/'false' -> dùng order_product_active_sql()
	$sql = "SELECT p.id, p.product_name, p.image_url,
	               v.id AS variant_id, v.variant_name, v.price, v.stock_quantity, v.image_url AS variant_image_url, v.group_id
	        FROM ecommerce_product p
	        LEFT JOIN ecommerce_product_variants v ON v.product_id = p.id AND v.status = 1
	        WHERE " . order_product_active_sql('p') . " AND (p.product_name LIKE ? OR v.sku_variant LIKE ?)
	        ORDER BY p.product_name ASC, v.id ASC
	        LIMIT 200";
	$stmt = $ithanhloc->prepare($sql);
	if (!$stmt) jOut(['ok' => false, 'msg' => 'DB error']);
	$stmt->bind_param('ss', $like, $like);
	$stmt->execute();
	$res = $stmt->get_result();
	$rows = [];
	if ($res instanceof mysqli_result) {
		while ($r = $res->fetch_assoc()) {
			$pid = (int)($r['id'] ?? 0);
			if (!isset($rows[$pid])) {
				$rows[$pid] = [
					'id' => $pid,
					'product_name' => (string)($r['product_name'] ?? ''),
					'image_url' => (string)($r['image_url'] ?? ''),
					'variants' => [],
				];
			}
			if ($r['variant_id']) {
				$rows[$pid]['variants'][] = [
					'variant_id' => (int)$r['variant_id'],
					'variant_name' => (string)($r['variant_name'] ?? ''),
					'price' => (float)($r['price'] ?? 0),
					'stock_quantity' => (int)($r['stock_quantity'] ?? 0),
					'image_url' => (string)($r['variant_image_url'] ?? ''),
					'group_id' => (int)($r['group_id'] ?? 0),
				];
			}
		}
	}
	$stmt->close();
	jOut(['ok' => true, 'rows' => array_values($rows)]);
}

// Danh sách danh mục (cho luồng: Danh mục -> Sản phẩm -> Nhóm -> Phân loại)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'category_list') {
	$rows = [];
	$res = $ithanhloc->query("SELECT id, name FROM ecommerce_category WHERE status = 1 ORDER BY sort_order ASC, name ASC");
	if ($res instanceof mysqli_result) {
		while ($r = $res->fetch_assoc()) {
			$rows[] = ['id' => (int)$r['id'], 'name' => (string)($r['name'] ?? '')];
		}
	}
	jOut(['ok' => true, 'rows' => $rows]);
}

// Danh sách sản phẩm theo danh mục
if (isset($_GET['ajax']) && $_GET['ajax'] === 'products_by_category') {
	$catId = (int)($_GET['category_id'] ?? 0);
	if ($catId <= 0) jOut(['ok' => true, 'rows' => []]);
	$sql = "SELECT id, product_name, image_url FROM ecommerce_product
	        WHERE category_id = ? AND " . order_product_active_sql('') . "
	        ORDER BY product_name ASC LIMIT 500";
	$stmt = $ithanhloc->prepare($sql);
	if (!$stmt) jOut(['ok' => false, 'msg' => 'DB error']);
	$stmt->bind_param('i', $catId);
	$stmt->execute();
	$res = $stmt->get_result();
	$rows = [];
	if ($res instanceof mysqli_result) {
		while ($r = $res->fetch_assoc()) {
			$rows[] = [
				'id' => (int)$r['id'],
				'product_name' => (string)($r['product_name'] ?? ''),
				'image_url' => (string)($r['image_url'] ?? ''),
			];
		}
	}
	$stmt->close();
	jOut(['ok' => true, 'rows' => $rows]);
}

// Nhóm + phân loại của 1 sản phẩm (Nhóm -> Phân loại)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'product_variants') {
	$pid = (int)($_GET['product_id'] ?? 0);
	if ($pid <= 0) jOut(['ok' => true, 'groups' => [], 'variants' => [], 'product_name' => '', 'image_url' => '']);

	$pName = ''; $pImg = '';
	if ($stmtP = $ithanhloc->prepare("SELECT product_name, image_url FROM ecommerce_product WHERE id = ? LIMIT 1")) {
		$stmtP->bind_param('i', $pid);
		$stmtP->execute();
		if ($rp = $stmtP->get_result()->fetch_assoc()) {
			$pName = (string)($rp['product_name'] ?? '');
			$pImg = (string)($rp['image_url'] ?? '');
		}
		$stmtP->close();
	}

	$groups = [];
	if ($stmtG = $ithanhloc->prepare("SELECT id, name FROM ecommerce_product_variant_groups WHERE product_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC")) {
		$stmtG->bind_param('i', $pid);
		$stmtG->execute();
		$rg = $stmtG->get_result();
		if ($rg instanceof mysqli_result) {
			while ($g = $rg->fetch_assoc()) {
				$groups[] = ['id' => (int)$g['id'], 'name' => (string)($g['name'] ?? '')];
			}
		}
		$stmtG->close();
	}

	$variants = [];
	if ($stmtV = $ithanhloc->prepare("SELECT id, variant_name, price, stock_quantity, image_url, group_id FROM ecommerce_product_variants WHERE product_id = ? AND status = 1 ORDER BY id ASC")) {
		$stmtV->bind_param('i', $pid);
		$stmtV->execute();
		$rv = $stmtV->get_result();
		if ($rv instanceof mysqli_result) {
			while ($v = $rv->fetch_assoc()) {
				$variants[] = [
					'variant_id' => (int)$v['id'],
					'variant_name' => (string)($v['variant_name'] ?? ''),
					'price' => (float)($v['price'] ?? 0),
					'stock_quantity' => (int)($v['stock_quantity'] ?? 0),
					'image_url' => (string)($v['image_url'] ?? ''),
					'group_id' => (int)($v['group_id'] ?? 0),
				];
			}
		}
		$stmtV->close();
	}

	jOut(['ok' => true, 'product_name' => $pName, 'image_url' => $pImg, 'groups' => $groups, 'variants' => $variants]);
}

// Chi tiết yêu cầu trả hàng (admin xét duyệt)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'return_request_detail') {
	$orderId = trim((string)($_GET['order_id'] ?? ''));
	if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
	if (!tableExists($ithanhloc, 'ecommerce_order_return')) {
		jOut(['ok' => true, 'request' => null]);
	}
	$stmtR = $ithanhloc->prepare(
		'SELECT id, order_id, user_id, reason, bank_account_id, bank_snapshot, refund_amount,
		        description, media_json, contact_email, status, created_at
		 FROM ecommerce_order_return
		 WHERE order_id=? ORDER BY id DESC LIMIT 1'
	);
	if (!$stmtR) jOut(['ok' => false, 'msg' => 'Không thể tải yêu cầu trả hàng']);
	$stmtR->bind_param('s', $orderId);
	$stmtR->execute();
	$req = $stmtR->get_result()->fetch_assoc();
	$stmtR->close();
	if (!$req) jOut(['ok' => true, 'request' => null]);

	$bank = json_decode((string)($req['bank_snapshot'] ?? ''), true);
	$media = json_decode((string)($req['media_json'] ?? ''), true);
	if (!is_array($bank)) $bank = null;
	if (!is_array($media)) $media = [];

	// Chuẩn hoá URL media (tương đối → tuyệt đối)
	$baseUrlNorm = rtrim((string)($baseUrl ?? ''), '/');
	$media = array_map(function ($m) use ($baseUrlNorm) {
		$u = (string)($m['url'] ?? '');
		if ($u !== '' && !preg_match('#^https?://#i', $u) && strpos($u, '//') !== 0) {
			$u = $baseUrlNorm . '/' . ltrim($u, '/');
		}
		return [
			'type' => (string)($m['type'] ?? 'image'),
			'url'  => $u,
		];
	}, $media);

	$req['refund_amount_fmt'] = fmtMoney((int)($req['refund_amount'] ?? 0));
	$req['created_fmt'] = !empty($req['created_at']) ? date('H:i d/m/Y', strtotime($req['created_at'])) : '';

	// Tra cứu các event đã ghi trong ecommerce_order_log để biết tiến trình xử lý
	$progress = [
		'approved'   => false,
		'received'   => false,
		'inspected'  => false,
		'completed'  => false,
	];
	$progressEvents = [];
	$stmtP = $ithanhloc->prepare(
		"SELECT event, note, created_at FROM ecommerce_order_log
		 WHERE order_id=? AND event IN ('return_approved','return_received','return_inspected')
		 ORDER BY id ASC"
	);
	if ($stmtP) {
		$stmtP->bind_param('s', $orderId);
		$stmtP->execute();
		$rp = $stmtP->get_result();
		while ($r = $rp->fetch_assoc()) {
			$evName = (string)$r['event'];
			if ($evName === 'return_approved')  $progress['approved']  = true;
			if ($evName === 'return_received')  $progress['received']  = true;
			if ($evName === 'return_inspected') $progress['inspected'] = true;
			$progressEvents[] = [
				'event' => $evName,
				'note'  => (string)$r['note'],
				'time'  => (string)$r['created_at'],
				'time_human' => date('H:i d/m/Y', strtotime((string)$r['created_at'])),
			];
		}
		$stmtP->close();
	}
	// Đơn đã chuyển sang 'returned' nghĩa là đã hoàn tất
	$stmtS = $ithanhloc->prepare("SELECT status FROM ecommerce_order WHERE order_id=? LIMIT 1");
	if ($stmtS) {
		$stmtS->bind_param('s', $orderId);
		$stmtS->execute();
		$rs = $stmtS->get_result()->fetch_assoc();
		$stmtS->close();
		if ($rs && (string)$rs['status'] === 'returned') {
			$progress['completed'] = true;
		}
		$req['order_status'] = $rs['status'] ?? '';
	}
	$req['progress'] = $progress;
	$req['progress_events'] = $progressEvents;
	$req['bank'] = $bank;
	$req['media'] = $media;
	unset($req['bank_snapshot'], $req['media_json']);

	jOut(['ok' => true, 'request' => $req]);
}

// Lightweight product list for the order modal (admin UI)
if (isset($_GET['get_products']) && $_GET['get_products'] === '1') {
	$data = [];
	$res = $ithanhloc->query("SELECT id, product_name, image_url FROM ecommerce_product ORDER BY id DESC LIMIT 2000");
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$name = trim((string)($row['product_name'] ?? ''));
			if ($name === '') continue;
			$data[] = ['id' => $row['id'], 'name' => $name, 'image_url' => (string)($row['image_url'] ?? '')];
		}
	}
	jOut(['ok' => true, 'data' => $data]);
}

if (isset($_REQUEST['action'])) {
	
	$action = $_REQUEST['action'];
	$payload = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

	$orderIdInput = trim($payload['order_id'] ?? '');
	$userId = (string)($_SESSION['user_id'] ?? '0');
	$userName = trim($payload['user_name'] ?? '');
	$phone = trim($payload['phone'] ?? '');
	$email = trim($payload['email'] ?? '');
	$address = trim($payload['address'] ?? '');
	$note = trim($payload['note'] ?? '');
	$product = trim($payload['product'] ?? '');
	
	$status = $payload['status'] ?? 'pending';
	$contact = trim($payload['contact'] ?? $phone);
	$paymentMethod = trim($payload['payment_method'] ?? '');
	$paymentStatus = trim($payload['payment_status'] ?? '');
	$paymentGateway = trim($payload['payment_gateway'] ?? '');
	$paymentRef = trim($payload['payment_ref'] ?? '');
	$bankCode = trim($payload['bank_code'] ?? '');
	$subtotal = normalizeDecimalInput($payload['subtotal'] ?? null);
	$shippingFee = normalizeDecimalInput($payload['shipping_fee'] ?? null);
	$shippingRuleIdRaw = trim($payload['shipping_rule_id'] ?? '');
	$shippingRuleId = ($shippingRuleIdRaw === '') ? null : (int)$shippingRuleIdRaw;
	$shippingMethod = trim($payload['shipping_method'] ?? '');
	$shippingMethodLabel = trim($payload['shipping_method_label'] ?? '');
	$totalAmount = normalizeDecimalInput($payload['total_amount'] ?? null);
	$shippingCarrier = trim($payload['shipping_carrier'] ?? '');
	$shippingEta = trim($payload['shipping_eta'] ?? '');
	$shippingSnapshotJson = trim($payload['shipping_snapshot_json'] ?? '');
	$shippingTracking = trim($payload['shipping_tracking'] ?? '');
	

	if ($action === 'save') {
		if ($userName === '') {
			jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên khách hàng']);
		}

		$isUpdate = $orderIdInput !== '' && orderExists($ithanhloc, $orderIdInput);
		$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
		$productsJson = normalizeProductsJson($payload['products_json'] ?? '[]');
		if ($isUpdate) {
			$set = [];
			$params = [];
			$types = '';

			$set[] = 'user_name=?'; $params[] = $userName; $types .= 's';
			if (isset($cols['phone'])) { $set[] = 'phone=?'; $params[] = $phone; $types .= 's'; }
			if (isset($cols['email'])) { $set[] = 'email=?'; $params[] = $email; $types .= 's'; }
			if (isset($cols['address'])) { $set[] = 'address=?'; $params[] = $address; $types .= 's'; }
			if (isset($cols['note'])) { $set[] = 'note=?'; $params[] = $note; $types .= 's'; }

			// Handle status update via applyStatusTransition for side effects
			$currentStatus = '';
			$stCheck = $ithanhloc->query("SELECT status FROM ecommerce_order WHERE order_id='{$ithanhloc->real_escape_string($orderIdInput)}'");
			if ($stCheck && $row = $stCheck->fetch_assoc()) {
				$currentStatus = (string)($row['status'] ?? '');
			}
			if ($currentStatus !== $status || $status === 'shipping') {
				applyStatusTransition($ithanhloc, $orderIdInput, $status, $shippingCarrier, $shippingTracking);
			}
			
			if (isset($cols['contact'])) { $set[] = 'contact=?'; $params[] = $contact; $types .= 's'; }
			if (isset($cols['payment_method']) && payloadHas($payload, 'payment_method')) { $set[] = 'payment_method=?'; $params[] = $paymentMethod; $types .= 's'; }
			if (isset($cols['payment_status']) && payloadHas($payload, 'payment_status')) { $set[] = 'payment_status=?'; $params[] = $paymentStatus; $types .= 's'; }
			if (isset($cols['payment_gateway']) && payloadHas($payload, 'payment_gateway')) { $set[] = 'payment_gateway=?'; $params[] = $paymentGateway; $types .= 's'; }
			if (isset($cols['payment_ref']) && payloadHas($payload, 'payment_ref')) { $set[] = 'payment_ref=?'; $params[] = $paymentRef; $types .= 's'; }
			if (isset($cols['bank_code']) && payloadHas($payload, 'bank_code')) { $set[] = 'bank_code=?'; $params[] = $bankCode; $types .= 's'; }
			if (isset($cols['subtotal']) && payloadHas($payload, 'subtotal')) {
				if ($subtotal === null) {
					$set[] = 'subtotal=NULL';
				} else {
					$set[] = 'subtotal=?';
					$params[] = $subtotal;
					$types .= 's';
				}
			}
			if (isset($cols['shipping_fee']) && payloadHas($payload, 'shipping_fee')) {
				if ($shippingFee === null) {
					$set[] = 'shipping_fee=NULL';
				} else {
					$set[] = 'shipping_fee=?';
					$params[] = $shippingFee;
					$types .= 's';
				}
			}
			if (isset($cols['shipping_rule_id']) && payloadHas($payload, 'shipping_rule_id')) {
				if ($shippingRuleId === null) {
					$set[] = 'shipping_rule_id=NULL';
				} else {
					$set[] = 'shipping_rule_id=?';
					$params[] = $shippingRuleId;
					$types .= 'i';
				}
			}
			if (isset($cols['shipping_method']) && payloadHas($payload, 'shipping_method')) { $set[] = 'shipping_method=?'; $params[] = $shippingMethod; $types .= 's'; }
			if (isset($cols['shipping_method_label']) && payloadHas($payload, 'shipping_method_label')) { $set[] = 'shipping_method_label=?'; $params[] = $shippingMethodLabel; $types .= 's'; }
			if (isset($cols['total_amount']) && payloadHas($payload, 'total_amount')) {
				if ($totalAmount === null) {
					$set[] = 'total_amount=NULL';
				} else {
					$set[] = 'total_amount=?';
					$params[] = $totalAmount;
					$types .= 's';
				}
			}
			if (isset($cols['shipping_carrier']) && payloadHas($payload, 'shipping_carrier')) { $set[] = 'shipping_carrier=?'; $params[] = $shippingCarrier; $types .= 's'; }
			if (isset($cols['shipping_eta']) && payloadHas($payload, 'shipping_eta')) { $set[] = 'shipping_eta=?'; $params[] = $shippingEta; $types .= 's'; }
			if (isset($cols['shipping_snapshot_json']) && payloadHas($payload, 'shipping_snapshot_json')) { $set[] = 'shipping_snapshot_json=?'; $params[] = $shippingSnapshotJson; $types .= 's'; }
			if (isset($cols['shipping_tracking']) && payloadHas($payload, 'shipping_tracking')) { $set[] = 'shipping_tracking=?'; $params[] = $shippingTracking; $types .= 's'; }
			if (isset($cols['canceled_reason']) && payloadHas($payload, 'canceled_reason')) { $set[] = 'canceled_reason=?'; $params[] = (string)($payload['canceled_reason'] ?? ''); $types .= 's'; }
			

			// products
			if (isset($cols['products_json']) && payloadHas($payload, 'products_json')) {
				$set[] = 'products_json=?';
				$params[] = $productsJson;
				$types .= 's';
			} elseif (isset($cols['product']) && payloadHas($payload, 'product')) {
				$set[] = 'product=?';
				$params[] = $product;
				$types .= 's';
			}

			if (isset($cols['updated_at'])) {
				$set[] = 'updated_at=NOW()';
			}

			$params[] = $orderIdInput;
			$types .= 's';
			$sql = 'UPDATE ecommerce_order SET ' . implode(',', $set) . ' WHERE order_id=?';
			$stmt = $ithanhloc->prepare($sql);
			if (!$stmt) {
				jOut(['ok' => false, 'msg' => 'Prepare failed', 'err' => $ithanhloc->error]);
			}
			if (!bindParamsDynamic($stmt, $types, $params)) {
				jOut(['ok' => false, 'msg' => 'Bind failed']);
			}
			if ($stmt->execute()) {
				jOut(['ok' => true, 'msg' => 'Đã cập nhật đơn hàng', 'order_id' => $orderIdInput]);
			}
			jOut(['ok' => false, 'msg' => 'Không thể cập nhật', 'err' => $stmt->error]);
		}

		$newId = $orderIdInput !== '' ? $orderIdInput : ('DH-' . date('ymd') . rand(1000, 9999));
		$insCols = ['order_id', 'user_id', 'user_name'];
		$insVals = ['?', '?', '?'];
		$insParams = [$newId, $userId, $userName];
		$insTypes = 'sss';

		if (isset($cols['phone'])) { $insCols[]='phone'; $insVals[]='?'; $insParams[]=$phone; $insTypes.='s'; }
		if (isset($cols['email'])) { $insCols[]='email'; $insVals[]='?'; $insParams[]=$email; $insTypes.='s'; }
		if (isset($cols['address'])) { $insCols[]='address'; $insVals[]='?'; $insParams[]=$address; $insTypes.='s'; }
		if (isset($cols['note'])) { $insCols[]='note'; $insVals[]='?'; $insParams[]=$note; $insTypes.='s'; }
		if (isset($cols['status'])) { $insCols[]='status'; $insVals[]='?'; $insParams[]=$status; $insTypes.='s'; }
		if (isset($cols['contact'])) { $insCols[]='contact'; $insVals[]='?'; $insParams[]=$contact; $insTypes.='s'; }
		if (isset($cols['products_json'])) {
			$insCols[] = 'products_json';
			$insVals[] = '?';
			$insParams[] = $productsJson;
			$insTypes .= 's';
		} elseif (isset($cols['product'])) {
			$insCols[] = 'product';
			$insVals[] = '?';
			$insParams[] = $product;
			$insTypes .= 's';
		}

		if (isset($cols['payment_method'])) {
			$insCols[] = 'payment_method';
			$insVals[] = "'cod'";
		}
		if (isset($cols['shipping_carrier'])) { $insCols[]='shipping_carrier'; $insVals[]='?'; $insParams[]=$shippingCarrier; $insTypes.='s'; }
		if (isset($cols['shipping_rule_id'])) {
			$insCols[]='shipping_rule_id';
			if ($shippingRuleId === null) {
				$insVals[]='NULL';
			} else {
				$insVals[]='?';
				$insParams[]=$shippingRuleId;
				$insTypes.='i';
			}
		}
		if (isset($cols['shipping_method'])) { $insCols[]='shipping_method'; $insVals[]='?'; $insParams[]=$shippingMethod; $insTypes.='s'; }
		if (isset($cols['shipping_method_label'])) { $insCols[]='shipping_method_label'; $insVals[]='?'; $insParams[]=$shippingMethodLabel; $insTypes.='s'; }
		if (isset($cols['shipping_eta'])) { $insCols[]='shipping_eta'; $insVals[]='?'; $insParams[]=$shippingEta; $insTypes.='s'; }
		if (isset($cols['shipping_snapshot_json'])) { $insCols[]='shipping_snapshot_json'; $insVals[]='?'; $insParams[]=$shippingSnapshotJson; $insTypes.='s'; }
		if (isset($cols['shipping_tracking'])) { $insCols[]='shipping_tracking'; $insVals[]='?'; $insParams[]=$shippingTracking; $insTypes.='s'; }
		
		if (isset($cols['subtotal'])) { $insCols[]='subtotal'; $insVals[]='0'; }
		if (isset($cols['shipping_fee'])) { $insCols[]='shipping_fee'; $insVals[]='0'; }
		if (isset($cols['total_amount'])) { $insCols[]='total_amount'; $insVals[]='0'; }
		if (isset($cols['created_at'])) { $insCols[]='created_at'; $insVals[]='NOW()'; }
		if (isset($cols['updated_at'])) { $insCols[]='updated_at'; $insVals[]='NOW()'; }

		$sql = 'INSERT INTO ecommerce_order (' . implode(',', $insCols) . ') VALUES (' . implode(',', $insVals) . ')';
		$stmt = $ithanhloc->prepare($sql);
		if (!$stmt) {
			jOut(['ok' => false, 'msg' => 'Prepare failed', 'err' => $ithanhloc->error]);
		}
		if (!bindParamsDynamic($stmt, $insTypes, $insParams)) {
			jOut(['ok' => false, 'msg' => 'Bind failed']);
		}
		if ($stmt->execute()) {
			jOut(['ok' => true, 'msg' => 'Đã tạo đơn hàng', 'order_id' => $newId]);
		}
		jOut(['ok' => false, 'msg' => 'Không thể tạo đơn', 'err' => $stmt->error]);
	}

	if ($action === 'update_status') {
		$orderId = trim((string)($_POST['id'] ?? ''));
		$st = (string)($_POST['status'] ?? 'pending');
		$carrier = isset($_POST['shipping_carrier']) ? trim((string)$_POST['shipping_carrier']) : null;
		$tracking = isset($_POST['shipping_tracking']) ? trim((string)$_POST['shipping_tracking']) : null;
		jOut(applyStatusTransition($ithanhloc, $orderId, $st, $carrier, $tracking));
	}

	if ($action === 'admin_set_status') {
		$orderId = trim((string)($payload['order_id'] ?? ''));
		$st = (string)($payload['status'] ?? 'pending');
		$carrier = isset($payload['shipping_carrier']) ? trim((string)$payload['shipping_carrier']) : null;
		$tracking = isset($payload['shipping_tracking']) ? trim((string)$payload['shipping_tracking']) : null;
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
		jOut(applyStatusTransition($ithanhloc, $orderId, $st, $carrier, $tracking));
	}

	if ($action === 'admin_decide_return') {
		$orderId  = trim((string)($payload['order_id'] ?? ''));
		$decision = strtolower(trim((string)($payload['decision'] ?? '')));
		$adminNote = trim((string)($payload['note'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
		if (!in_array($decision, ['approve','reject'], true)) jOut(['ok' => false, 'msg' => 'Quyết định không hợp lệ (approve/reject)']);

		// Lấy trạng thái + user_id để xác thực và notify
		$stmtCur = $ithanhloc->prepare('SELECT status, user_id FROM ecommerce_order WHERE order_id=? LIMIT 1');
		if (!$stmtCur) jOut(['ok' => false, 'msg' => 'Không thể tải đơn']);
		$stmtCur->bind_param('s', $orderId);
		$stmtCur->execute();
		$curRow = $stmtCur->get_result()->fetch_assoc();
		$stmtCur->close();
		if (!$curRow) jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);
		if ((string)($curRow['status'] ?? '') !== 'return_requested') {
			jOut(['ok' => false, 'msg' => 'Đơn không ở trạng thái chờ duyệt trả hàng']);
		}

		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);
		$orderUserId = (int)($curRow['user_id'] ?? 0);

		// Cập nhật trạng thái yêu cầu trả hàng trong ecommerce_order_return
		if (tableExists($ithanhloc, 'ecommerce_order_return')) {
			// Tự bổ sung các cột còn thiếu (admin_note/decided_by/decided_at)
			$existingCols = [];
			if ($colRes = $ithanhloc->query("SHOW COLUMNS FROM ecommerce_order_return")) {
				while ($c = $colRes->fetch_assoc()) {
					$existingCols[strtolower($c['Field'])] = true;
				}
				$colRes->free();
			}
			$alters = [];
			if (!isset($existingCols['admin_note']))  $alters[] = 'ADD COLUMN `admin_note` TEXT NULL';
			if (!isset($existingCols['decided_by']))  $alters[] = 'ADD COLUMN `decided_by` INT(11) NULL';
			if (!isset($existingCols['decided_at']))  $alters[] = 'ADD COLUMN `decided_at` DATETIME NULL';
			if ($alters) {
				try { $ithanhloc->query('ALTER TABLE ecommerce_order_return ' . implode(', ', $alters)); }
				catch (Throwable $e) { error_log('ALTER ecommerce_order_return failed: ' . $e->getMessage()); }
			}

			$newReqStatus = $decision === 'approve' ? 'approved' : 'rejected';
			try {
				$stmtUR = $ithanhloc->prepare(
					'UPDATE ecommerce_order_return
					 SET status=?, admin_note=?, decided_by=?, decided_at=NOW()
					 WHERE order_id=? ORDER BY id DESC LIMIT 1'
				);
				if ($stmtUR) {
					$stmtUR->bind_param('ssis', $newReqStatus, $adminNote, $actorId, $orderId);
					$stmtUR->execute();
					$stmtUR->close();
				}
			} catch (Throwable $e) {
				error_log('UPDATE ecommerce_order_return failed: ' . $e->getMessage());
			}
		}

		if ($decision === 'approve') {
			// Chỉ ghi event return_approved — KHÔNG transition status (vẫn ở return_requested).
			// Hoàn kho chỉ thực hiện ở bước cuối khi admin xác nhận hoàn tất qua admin_confirm_return_completed.
			ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
				'return_approved', 'return_requested', 'return_requested',
				'Đã duyệt yêu cầu trả hàng — chờ nhận hàng từ khách' . ($adminNote !== '' ? '. Ghi chú: ' . $adminNote : ''));
			if ($orderUserId > 0) {
				app_user_notify_template($ithanhloc, $orderUserId, 'order_return_approved', [
					'order_id'     => $orderId,
					'status'       => 'return_requested',
					'status_label' => 'Đã duyệt yêu cầu trả hàng',
					'admin_note'   => $adminNote,
					'time'         => date('Y-m-d H:i:s'),
					'link'         => '/view-order?order_id=' . urlencode($orderId),
					'event'        => 'order_return_approved',
				]);
			}
			jOut(['ok' => true, 'msg' => 'Đã duyệt yêu cầu trả hàng. Chờ nhận lại hàng từ khách.']);
		}

		// Từ chối → quay về delivered
		$res = applyStatusTransition($ithanhloc, $orderId, 'delivered', null, null);
		if (!empty($res['ok'])) {
			ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
				'return_rejected', 'return_requested', 'delivered',
				'Từ chối yêu cầu trả hàng' . ($adminNote !== '' ? ': ' . $adminNote : ''));
			if ($orderUserId > 0) {
				app_user_notify_template($ithanhloc, $orderUserId, 'order_return_rejected', [
					'order_id'     => $orderId,
					'status'       => 'delivered',
					'status_label' => 'Yêu cầu trả hàng bị từ chối',
					'admin_note'   => $adminNote,
					'time'         => date('Y-m-d H:i:s'),
					'link'         => '/view-order?order_id=' . urlencode($orderId),
					'event'        => 'order_return_rejected',
				]);
			}
		}
		jOut($res);
	}

	// Các bước trung gian xử lý trả hàng (sau khi đã duyệt return_approved)
	// - admin_mark_return_received: Khi shop đã nhận lại hàng từ khách
	// - admin_mark_return_inspected: Sau khi kiểm tra hàng hoàn (đạt yêu cầu)
	// - admin_confirm_return_completed: Chốt hoàn tất → transition sang 'returned' (lúc này mới restock)
	if (in_array($action, ['admin_mark_return_received', 'admin_mark_return_inspected', 'admin_confirm_return_completed'], true)) {
		$orderId   = trim((string)($payload['order_id'] ?? ''));
		$adminNote = trim((string)($payload['note'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);

		$stmtCur = $ithanhloc->prepare('SELECT status, user_id FROM ecommerce_order WHERE order_id=? LIMIT 1');
		if (!$stmtCur) jOut(['ok' => false, 'msg' => 'Không thể tải đơn']);
		$stmtCur->bind_param('s', $orderId);
		$stmtCur->execute();
		$curRow = $stmtCur->get_result()->fetch_assoc();
		$stmtCur->close();
		if (!$curRow) jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);
		$curStatus = (string)($curRow['status'] ?? '');

		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);
		$orderUserId = (int)($curRow['user_id'] ?? 0);

		// Map action → event/label/template để xử lý chung
		$flowMap = [
			'admin_mark_return_received' => [
				'event'        => 'return_received',
				'admin_msg'    => 'Đã nhận lại hàng từ khách',
				'user_label'   => 'Shop đã nhận lại hàng hoàn',
				'tpl'          => 'order_return_received',
				'requires'     => ['return_requested'],
				'success_msg'  => 'Đã đánh dấu nhận lại hàng. Tiếp theo: kiểm tra hàng hoàn.',
			],
			'admin_mark_return_inspected' => [
				'event'        => 'return_inspected',
				'admin_msg'    => 'Đã kiểm tra hàng hoàn',
				'user_label'   => 'Shop đã kiểm tra hàng hoàn',
				'tpl'          => 'order_return_inspected',
				'requires'     => ['return_requested'],
				'success_msg'  => 'Đã ghi nhận kiểm tra. Tiếp theo: xác nhận hoàn tất trả hàng.',
			],
			'admin_confirm_return_completed' => [
				'event'        => 'status_changed', // do applyStatusTransition tự ghi
				'admin_msg'    => 'Xác nhận hoàn tất trả hàng',
				'user_label'   => 'Đã xác nhận hoàn tất trả hàng',
				'tpl'          => 'order_return_completed',
				'requires'     => ['return_requested'],
				'success_msg'  => 'Đã xác nhận hoàn tất trả hàng. Kho đã được cập nhật.',
			],
		];
		$cfg = $flowMap[$action];

		if (!in_array($curStatus, $cfg['requires'], true)) {
			jOut(['ok' => false, 'msg' => 'Đơn không ở trạng thái phù hợp để thực hiện thao tác này.']);
		}

		if ($action === 'admin_confirm_return_completed') {
			// Bước cuối: transition sang 'returned' → applyStatusTransition tự gọi restoreStockForOrder
			$res = applyStatusTransition($ithanhloc, $orderId, 'returned', null, null);
			if (!empty($res['ok']) && $orderUserId > 0) {
				app_user_notify_template($ithanhloc, $orderUserId, $cfg['tpl'], [
					'order_id'     => $orderId,
					'status'       => 'returned',
					'status_label' => $cfg['user_label'],
					'admin_note'   => $adminNote,
					'time'         => date('Y-m-d H:i:s'),
					'link'         => '/view-order?order_id=' . urlencode($orderId),
					'event'        => $cfg['tpl'],
				]);
			}
			$res['msg'] = !empty($res['ok']) ? $cfg['success_msg'] : ($res['msg'] ?? 'Thao tác thất bại');
			jOut($res);
		}

		// 2 bước trung gian: chỉ ghi event log + notify, KHÔNG transition status
		$note = $cfg['admin_msg'] . ($adminNote !== '' ? '. Ghi chú: ' . $adminNote : '');
		ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
			$cfg['event'], $curStatus, $curStatus, $note);

		if ($orderUserId > 0) {
			app_user_notify_template($ithanhloc, $orderUserId, $cfg['tpl'], [
				'order_id'     => $orderId,
				'status'       => $curStatus,
				'status_label' => $cfg['user_label'],
				'admin_note'   => $adminNote,
				'time'         => date('Y-m-d H:i:s'),
				'link'         => '/view-order?order_id=' . urlencode($orderId),
				'event'        => $cfg['tpl'],
			]);
		}

		jOut(['ok' => true, 'msg' => $cfg['success_msg']]);
	}

	if ($action === 'admin_refund_via_gateway') {
		require_once __DIR__ . '/../lib/refund_gateways.php';
		$orderId = trim((string)($payload['order_id'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);
		$res = refund_apply_to_order($ithanhloc, $orderId, $actorId);
		jOut([
			'ok' => !empty($res['ok']),
			'status' => $res['status'] ?? 'failed',
			'msg' => $res['msg'] ?? 'Refund thất bại',
			'refund_id' => $res['refund_id'] ?? 0,
			'gateway_refund_id' => $res['gateway_refund_id'] ?? '',
		]);
	}

	if ($action === 'admin_mark_refunded') {
		$orderId = trim((string)($payload['order_id'] ?? ''));
		$adminNote = trim((string)($payload['note'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);

		$stmtCur = $ithanhloc->prepare('SELECT status, user_id, payment_status, payment_gateway, payment_method, total_amount FROM ecommerce_order WHERE order_id=? LIMIT 1');
		if (!$stmtCur) jOut(['ok' => false, 'msg' => 'Không thể tải đơn']);
		$stmtCur->bind_param('s', $orderId);
		$stmtCur->execute();
		$curRow = $stmtCur->get_result()->fetch_assoc();
		$stmtCur->close();
		if (!$curRow) jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);

		$ps = strtolower(trim((string)($curRow['payment_status'] ?? '')));
		if ($ps !== 'refund_pending') {
			jOut(['ok' => false, 'msg' => 'Đơn không ở trạng thái cần hoàn tiền (hiện tại: ' . ($ps ?: 'unknown') . ')']);
		}

		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);

		$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
		$setSql = "payment_status='refunded'";
		if (isset($cols['refunded_at'])) $setSql .= ", refunded_at=COALESCE(refunded_at, NOW())";
		if (isset($cols['updated_at'])) $setSql .= ", updated_at=NOW()";
		$stmtU = $ithanhloc->prepare("UPDATE ecommerce_order SET $setSql WHERE order_id=? LIMIT 1");
		if (!$stmtU) jOut(['ok' => false, 'msg' => 'Không thể cập nhật trạng thái thanh toán']);
		$stmtU->bind_param('s', $orderId);
		$ok = $stmtU->execute();
		$stmtU->close();
		if (!$ok) jOut(['ok' => false, 'msg' => 'Cập nhật thất bại']);

		$gateway = strtoupper((string)($curRow['payment_gateway'] ?? $curRow['payment_method'] ?? ''));
		$amount = (float)($curRow['total_amount'] ?? 0);
		$note = sprintf('Xác nhận đã hoàn tiền %s qua %s', number_format($amount, 0, ',', '.') . ' đ', $gateway ?: 'cổng thanh toán')
			. ($adminNote !== '' ? '. Ghi chú: ' . $adminNote : '');
		ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
			'refund_completed', (string)$curRow['status'], (string)$curRow['status'], $note);

		$orderUserId = (int)($curRow['user_id'] ?? 0);
		if ($orderUserId > 0 && function_exists('app_user_notify_template')) {
			app_user_notify_template($ithanhloc, $orderUserId, 'order_refunded', [
				'order_id'   => $orderId,
				'amount'     => number_format($amount, 0, '.', ''),
				'gateway'    => $gateway,
				'time'       => date('Y-m-d H:i:s'),
				'link'       => '/view-order?order_id=' . urlencode($orderId),
				'event'      => 'order_refunded',
			]);
		}

		jOut(['ok' => true, 'msg' => 'Đã ghi nhận hoàn tiền đơn ' . $orderId]);
	}

	if ($action === 'admin_decide_cancel') {
		$orderId  = trim((string)($payload['order_id'] ?? ''));
		$decision = strtolower(trim((string)($payload['decision'] ?? '')));
		$adminNote = trim((string)($payload['note'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
		if (!in_array($decision, ['approve', 'reject'], true)) jOut(['ok' => false, 'msg' => 'Quyết định không hợp lệ (approve/reject)']);

		// Fetch current status + user_id to validate
		$stmtCur = $ithanhloc->prepare('SELECT status, user_id FROM ecommerce_order WHERE order_id=? LIMIT 1');
		$stmtCur->bind_param('s', $orderId);
		$stmtCur->execute();
		$curRow = $stmtCur->get_result()->fetch_assoc();
		$stmtCur->close();
		if (!$curRow) jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);
		if ((string)($curRow['status'] ?? '') !== 'cancel_requested') jOut(['ok' => false, 'msg' => 'Đơn hàng không ở trạng thái chờ duyệt hủy']);

		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);
		$orderUserId = (int)($curRow['user_id'] ?? 0);

		if ($decision === 'approve') {
			// Duyệt hủy → canceled
			$res = applyStatusTransition($ithanhloc, $orderId, 'canceled', null, null);
			if (!empty($res['ok'])) {
				ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
					'cancel_approved', 'cancel_requested', 'canceled',
					'Duyệt yêu cầu hủy đơn' . ($adminNote !== '' ? ': ' . $adminNote : ''));
				// Thông báo cho khách
				if ($orderUserId > 0) {
					app_user_notify_template($ithanhloc, $orderUserId, 'order_canceled', [
						'order_id'     => $orderId,
						'status'       => 'canceled',
						'status_label' => 'Đã hủy',
						'time'         => date('Y-m-d H:i:s'),
						'link'         => '/view-order?order_id=' . urlencode($orderId),
						'event'        => 'order_canceled',
					]);
				}
			}
			jOut($res);
		}

		// Từ chối hủy → quay về processing (đơn đã được xác nhận trước đó)
		$res = applyStatusTransition($ithanhloc, $orderId, 'processing', null, null);
		if (!empty($res['ok'])) {
			ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
				'cancel_rejected', 'cancel_requested', 'processing',
				'Từ chối yêu cầu hủy đơn' . ($adminNote !== '' ? ': ' . $adminNote : ''));
			if ($orderUserId > 0) {
				app_user_notify_template($ithanhloc, $orderUserId, 'order_status_updated', [
					'order_id'     => $orderId,
					'status'       => 'processing',
					'status_label' => 'Đang chuẩn bị hàng',
					'time'         => date('Y-m-d H:i:s'),
					'link'         => '/view-order?order_id=' . urlencode($orderId),
					'event'        => 'cancel_rejected',
				]);
			}
		}
		jOut($res);
	}

	if ($action === 'admin_save_note') {
		$orderId = trim((string)($payload['order_id'] ?? ''));
		$noteVal = trim((string)($payload['admin_note'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);

		$cols = array_flip(listColumns($ithanhloc, 'ecommerce_order'));
		if (!isset($cols['admin_note'])) {
			// Auto-migrate nếu chưa có cột (an toàn cho môi trường khác)
			$ithanhloc->query("ALTER TABLE ecommerce_order ADD COLUMN admin_note TEXT NULL");
		}

		$stmt = $ithanhloc->prepare('UPDATE ecommerce_order SET admin_note=?, updated_at=NOW() WHERE order_id=? LIMIT 1');
		if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể lưu ghi chú']);
		$stmt->bind_param('ss', $noteVal, $orderId);
		$ok = $stmt->execute();
		$stmt->close();
		if (!$ok) jOut(['ok' => false, 'msg' => 'Lưu ghi chú thất bại']);

		global $__sessionUserId;
		ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', (int)($__sessionUserId ?? 0),
			'admin_note_updated', '', '', $noteVal !== '' ? 'Cập nhật ghi chú nội bộ' : 'Xoá ghi chú nội bộ');
		jOut(['ok' => true, 'msg' => 'Đã lưu ghi chú nội bộ']);
	}

	if ($action === 'save_review_reply') {
		$orderId = trim((string)($payload['order_id'] ?? ''));
		$reply = trim((string)($payload['admin_reply'] ?? ''));
		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);

		$stmt = $ithanhloc->prepare('SELECT id FROM ecommerce_order_review WHERE order_id=? LIMIT 1');
		if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể kiểm tra đánh giá']);
		$stmt->bind_param('s', $orderId);
		$stmt->execute();
		$has = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$has) jOut(['ok' => false, 'msg' => 'Chưa có đánh giá cho đơn này']);

		$stmt = $ithanhloc->prepare("UPDATE ecommerce_order_review SET admin_reply=?, replied_at=IF(?='', NULL, NOW()) WHERE order_id=?");
		if (!$stmt) jOut(['ok' => false, 'msg' => 'Không thể lưu phản hồi']);
		$stmt->bind_param('sss', $reply, $reply, $orderId);
		if ($stmt->execute()) jOut(['ok' => true, 'msg' => 'Đã lưu phản hồi']);
		jOut(['ok' => false, 'msg' => 'Không thể lưu phản hồi']);
	}

	if ($action === 'admin_update_address') {
		$orderId        = trim((string)($payload['order_id'] ?? ''));
		$recipientName  = trim((string)($payload['recipient_name'] ?? ''));
		$contactPhone   = trim((string)($payload['contact_phone'] ?? ''));
		$street         = trim((string)($payload['street'] ?? ''));
		$ward           = trim((string)($payload['ward'] ?? ''));
		$wardCode       = trim((string)($payload['ward_code'] ?? ''));
		$district       = trim((string)($payload['district'] ?? ''));
		$districtId     = (int)($payload['district_id'] ?? 0);
		$province       = trim((string)($payload['province'] ?? ''));
		$provinceId     = (int)($payload['province_id'] ?? 0);

		if ($orderId === '') jOut(['ok' => false, 'msg' => 'Thiếu mã đơn hàng']);
		if ($recipientName === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập họ tên người nhận']);
		if ($contactPhone === '' || strlen(preg_replace('/[^0-9]/', '', $contactPhone)) < 9)
			jOut(['ok' => false, 'msg' => 'Số điện thoại không hợp lệ']);
		if ($provinceId <= 0) jOut(['ok' => false, 'msg' => 'Vui lòng chọn Tỉnh/Thành phố']);
		if ($districtId <= 0) jOut(['ok' => false, 'msg' => 'Vui lòng chọn Quận/Huyện']);
		if ($wardCode === '') jOut(['ok' => false, 'msg' => 'Vui lòng chọn Phường/Xã']);
		if ($street === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập địa chỉ chi tiết']);

		// Kiểm tra đơn tồn tại
		$stmtChk = $ithanhloc->prepare('SELECT status, shipping_snapshot_json FROM ecommerce_order WHERE order_id=? LIMIT 1');
		if (!$stmtChk) jOut(['ok' => false, 'msg' => 'Lỗi hệ thống']);
		$stmtChk->bind_param('s', $orderId);
		$stmtChk->execute();
		$orderRow = $stmtChk->get_result()->fetch_assoc();
		$stmtChk->close();
		if (!$orderRow) jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);
		$currentStatus = (string)(ecommerce_order_status_info($orderRow['status'] ?? 'pending')['key'] ?? 'pending');

		// Lookup tên tỉnh/quận/phường từ ghn_region nếu chưa có
		if ($province === '' && $provinceId > 0) {
			$s = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='province' AND region_id=? LIMIT 1");
			if ($s) { $s->bind_param('i', $provinceId); $s->execute(); $province = (string)($s->get_result()->fetch_assoc()['name'] ?? ''); $s->close(); }
		}
		if ($district === '' && $districtId > 0) {
			$s = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='district' AND region_id=? LIMIT 1");
			if ($s) { $s->bind_param('i', $districtId); $s->execute(); $district = (string)($s->get_result()->fetch_assoc()['name'] ?? ''); $s->close(); }
		}
		if ($ward === '' && $wardCode !== '') {
			$s = $ithanhloc->prepare("SELECT name FROM ghn_region WHERE level='ward' AND code=? LIMIT 1");
			if ($s) { $s->bind_param('s', $wardCode); $s->execute(); $ward = (string)($s->get_result()->fetch_assoc()['name'] ?? ''); $s->close(); }
		}

		$fullAddress  = implode(', ', array_filter([$street, $ward, $district, $province], static fn($v) => $v !== ''));
		$snapshot     = json_decode((string)($orderRow['shipping_snapshot_json'] ?? ''), true);
		if (!is_array($snapshot)) $snapshot = [];
		$snapshot['destination']          = $fullAddress;
		$snapshot['recipient_name']        = $recipientName;
		$snapshot['contact_phone']         = $contactPhone;
		$snapshot['province']              = $province;
		$snapshot['province_id']           = $provinceId;
		$snapshot['district']              = $district;
		$snapshot['district_id']           = $districtId;
		$snapshot['ward']                  = $ward;
		$snapshot['ward_code']             = $wardCode;
		$snapshot['street']                = $street;
		$snapshot['address_updated_at']    = date('Y-m-d H:i:s');
		$snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

		$stmtU = $ithanhloc->prepare(
			'UPDATE ecommerce_order SET address=?, user_name=?, phone=?, shipping_snapshot_json=?, updated_at=NOW() WHERE order_id=? LIMIT 1'
		);
		if (!$stmtU) jOut(['ok' => false, 'msg' => 'Lỗi hệ thống']);
		$stmtU->bind_param('sssss', $fullAddress, $recipientName, $contactPhone, $snapshotJson, $orderId);
		$ok  = $stmtU->execute();
		$err = $stmtU->error;
		$stmtU->close();

		if (!$ok) jOut(['ok' => false, 'msg' => $err ?: 'Không thể cập nhật địa chỉ']);

		global $__sessionUserId;
		$actorId = (int)($__sessionUserId ?? 0);
		ecommerce_order_log_insert($ithanhloc, $orderId, 'admin', $actorId,
			'address_updated', $currentStatus, $currentStatus,
			'Admin đổi địa chỉ: ' . $fullAddress . ' | SĐT: ' . $contactPhone . ' | Người nhận: ' . $recipientName);

		jOut(['ok' => true, 'msg' => 'Đã cập nhật địa chỉ giao hàng']);
	}

	if ($action === 'delete_one') {
		$id = trim((string)($_POST['id'] ?? ''));
		jOut(deleteOrderCascadeAdmin($ithanhloc, $id));
	}

	if ($action === 'delete_multi') {
		$ids = $_POST['ids'] ?? [];
		if (!is_array($ids) || !$ids) {
			jOut(['ok' => false, 'msg' => 'Thiếu danh sách đơn']);
		}
		$ids = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $ids), static fn($v) => $v !== ''));
		$deleted = 0;
		$failed = [];
		foreach ($ids as $id) {
			$res = deleteOrderCascadeAdmin($ithanhloc, $id);
			if (!($res['ok'] ?? false)) {
				$failed[] = $id;
				continue;
			}
			$deleted += (int)($res['deleted'] ?? 0);
		}
		jOut(['ok' => count($failed) === 0, 'deleted' => $deleted, 'failed' => $failed]);
	}
}

if (isset($_GET['export'])) {
	while (ob_get_level()) ob_end_clean();
	$ids = $_GET['ids'] ?? [];
	$where = '1=1';
	if ($ids) {
		$safe = array_map(fn($id) => "'" . $ithanhloc->real_escape_string($id) . "'", $ids);
		$where = 'order_id IN (' . implode(',', $safe) . ')';
	}
	$result = $ithanhloc->query("SELECT * FROM ecommerce_order WHERE $where ORDER BY created_at DESC");

	if ($_GET['export'] === 'csv') {
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=ecommerce_order_export.csv');
		$out = fopen('php://output', 'w');
		fputcsv($out, ['Order ID', 'Customer', 'Phone', 'Email', 'Products', 'Status', 'Created']);
		while ($row = $result->fetch_assoc()) {
			fputcsv($out, [$row['order_id'], $row['user_name'], $row['phone'], $row['email'], $row['product'], $row['status'], $row['created_at']]);
		}
		fclose($out);
		exit;
	}

	if ($_GET['export'] === 'print') {
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Danh sách đơn hàng</title><style>body{font-family:sans-serif;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #000;padding:8px;font-size:12px}th{background:#eee}</style></head><body onload="window.print()"><h3 style="text-align:center">DANH SÁCH ĐƠN HÀNG</h3><table><thead><tr><th>Mã ĐH</th><th>Khách hàng</th><th>SDT</th><th>Email</th><th>ản phẩm</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead><tbody>';
		while ($row = $result->fetch_assoc()) {
			echo '<tr>';
			echo '<td>' . h($row['order_id']) . '</td>';
			echo '<td>' . h($row['user_name']) . '</td>';
			echo '<td>' . h($row['phone']) . '</td>';
			echo '<td>' . h($row['email']) . '</td>';
			echo '<td>' . h($row['product']) . '</td>';
			echo '<td>' . h($row['status']) . '</td>';
			echo '<td>' . h($row['created_at']) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></body></html>';
		exit;
	}
}

http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Yêu cầu không hợp lệ']);

