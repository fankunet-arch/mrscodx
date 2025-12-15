<?php
/**
 * Backend Batch Print Preview Page
 * 文件路径: app/express/views/batch_print.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;

if (empty($batch_id)) {
    die('批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    die('批次不存在');
}

$packages = express_get_packages_by_batch($pdo, $batch_id, 'all');
$printable_packages = array_values(array_filter($packages, function ($package) {
    $note = trim($package['content_note'] ?? '');
    return $note !== '空';
}));

function express_tracking_tail($tracking_number)
{
    if (!$tracking_number) {
        return '----';
    }

    $tracking_number = trim((string) $tracking_number);

    if ($tracking_number === '') {
        return '----';
    }

    return substr($tracking_number, -4);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次打印预览 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #111;
        }

        .page-wrapper {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .print-title {
            font-size: 18px;
            font-weight: 600;
            color: #222;
        }

        .print-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 14px;
            border: 1px solid #d0d0d0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: #f1f1f1;
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60mm, 1fr));
            gap: 8mm 6mm;
        }

        .label-card {
            border: 1px solid #111;
            border-radius: 6px;
            padding: 6mm 5mm;
            min-height: 45mm;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: stretch;
            page-break-inside: avoid;
        }

        .label-title {
            font-size: 40pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
        }

        .label-meta {
            margin-top: 4mm;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 8mm;
            font-size: 18pt;
            font-weight: 600;
            line-height: 1.2;
        }

        .tracking-tail {
            font-size: 20pt;
            letter-spacing: 1px;
        }

        @media print {
            body {
                background: white;
            }

            .print-actions {
                display: none;
            }

            .page-wrapper {
                padding: 0;
            }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="print-header">
        <div class="print-title">批次：<?= htmlspecialchars($batch['batch_name']) ?>（共 <?= count($printable_packages) ?> 件）</div>
        <div class="print-actions">
            <button class="btn" onclick="window.print()">打印</button>
            <button class="btn" onclick="window.close()">关闭</button>
        </div>
    </div>

    <div class="label-grid">
        <?php foreach ($printable_packages as $package): ?>
            <?php
            $content = trim($package['content_note'] ?? '');
            $content = $content !== '' ? $content : '未填写内容备注';
            $tail = express_tracking_tail($package['tracking_number'] ?? '');
            ?>
            <div class="label-card">
                <div class="label-title"><?= htmlspecialchars($content) ?></div>
                <div class="label-meta">
                    <span class="tracking-tail">尾号：<?= htmlspecialchars($tail) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function adjustTitleSizes() {
        const titles = document.querySelectorAll('.label-title');
        titles.forEach(title => {
            let size = 40;
            const minSize = 18;
            title.style.fontSize = `${size}pt`;

            while (title.scrollWidth > title.clientWidth && size > minSize) {
                size -= 1;
                title.style.fontSize = `${size}pt`;
            }
        });
    })();
</script>
</body>
</html>
