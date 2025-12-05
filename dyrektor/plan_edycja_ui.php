<?php
require_once '../includes/config.php';
sprawdz_uprawnienia('dyrektor');

$current_page = 'plan_edycja_ui.php';

// Pobierz listę klas
$klasy = [];
$result = $conn->query("SELECT id, nazwa FROM klasy ORDER BY nazwa");
while ($row = $result->fetch_assoc()) {
    $klasy[] = $row;
}

// Domyślna data - bieżący tydzień
$data_od = isset($_GET['data_od']) ? $_GET['data_od'] : pobierz_poczatek_tygodnia(date('Y-m-d'));
$data_do = isset($_GET['data_do']) ? $_GET['data_do'] : pobierz_koniec_tygodnia(date('Y-m-d'));
$klasa_id = isset($_GET['klasa_id']) ? (int)$_GET['klasa_id'] : null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edycja Planu Lekcji - Panel Dyrektora</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Dodatkowe style dla edytora - modern gradient design */
        .editor-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
        }

        .editor-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .editor-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .schedule-editor {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }

        .schedule-editor th,
        .schedule-editor td {
            border: 1px solid rgba(102, 126, 234, 0.15);
            padding: 12px;
            text-align: center;
        }

        .schedule-editor th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 15px 12px;
        }

        .schedule-editor .lesson-cell {
            min-height: 85px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            vertical-align: top;
            background: white;
        }

        .schedule-editor .lesson-cell.empty {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(118, 75, 162, 0.02) 100%);
        }

        .schedule-editor .lesson-cell.empty:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .schedule-editor .lesson-cell:not(.empty) {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .schedule-editor .lesson-cell:not(.empty):hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.12) 100%);
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
        }

        .schedule-editor .lesson-cell.dragging {
            opacity: 0.5;
            background: linear-gradient(135deg, rgba(255, 87, 34, 0.1) 0%, rgba(244, 67, 54, 0.1) 100%);
            transform: scale(0.98);
        }

        .schedule-editor .lesson-cell.drag-over {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            border: 2px dashed #667eea;
            box-shadow: inset 0 0 0 2px rgba(102, 126, 234, 0.3);
        }

        .lesson-content {
            padding: 8px;
            font-size: 12px;
        }

        .lesson-content .przedmiot {
            font-weight: 600;
            color: #667eea;
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .lesson-content .nauczyciel {
            color: #555;
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .lesson-content .sala {
            color: #888;
            font-size: 11px;
            display: block;
            font-style: italic;
        }

        .lesson-actions {
            position: absolute;
            top: 6px;
            right: 6px;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            gap: 4px;
        }

        .lesson-cell:hover .lesson-actions {
            opacity: 1;
        }

        .lesson-actions button {
            background: white;
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 6px;
            cursor: pointer;
            padding: 4px 8px;
            font-size: 14px;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .lesson-actions button:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .lesson-actions .edit-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .lesson-actions .delete-btn:hover {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            border-color: transparent;
        }

        .time-column {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            font-weight: 600;
            width: 120px;
            color: #667eea;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(102, 126, 234, 0.3);
            backdrop-filter: blur(4px);
            overflow: auto;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 35px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.25);
            animation: slideUp 0.3s;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(90deg, #667eea 0%, #764ba2 100%) 1;
        }

        .modal-header h3 {
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }

        .close {
            color: #999;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1;
        }

        .close:hover {
            color: #667eea;
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin: 12px 0;
            font-size: 14px;
            font-weight: 500;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-10px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(56, 142, 60, 0.1) 100%);
            border: 2px solid rgba(76, 175, 80, 0.3);
            color: #2e7d32;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1) 0%, rgba(211, 47, 47, 0.1) 100%);
            border: 2px solid rgba(244, 67, 54, 0.3);
            color: #c62828;
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.1) 0%, rgba(245, 124, 0, 0.1) 100%);
            border: 2px solid rgba(255, 152, 0, 0.3);
            color: #ef6c00;
        }

        .conflicts-list {
            max-height: 350px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .conflicts-list::-webkit-scrollbar {
            width: 6px;
        }

        .conflicts-list::-webkit-scrollbar-track {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 3px;
        }

        .conflicts-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
        }

        .conflict-item {
            padding: 12px;
            margin-bottom: 12px;
            border-left: 4px solid;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 0 8px 8px 0;
            transition: all 0.2s;
        }

        .conflict-item:hover {
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .conflict-nauczyciel {
            border-color: #f44336;
        }

        .conflict-sala {
            border-color: #ff9800;
        }

        .conflict-klasa {
            border-color: #667eea;
        }

        .conflict-dostepnosc {
            border-color: #9c27b0;
        }

        .conflict-wymiar_godzin {
            border-color: #ff5722;
        }

        .loader {
            display: none;
            border: 4px solid rgba(102, 126, 234, 0.2);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #388e3c 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-header">
                <h1>Edycja Planu Lekcji</h1>
                <div class="user-info">
                    <span>Witaj, <?php echo e($_SESSION['user_name']); ?>!</span>
                    <a href="../logout.php" class="btn-logout">Wyloguj</a>
                </div>
            </header>

            <div class="admin-content">
                <!-- Nagłówek edytora -->
                <div class="editor-header">
                    <div class="editor-controls">
                        <select id="klasa-select" class="form-control" style="min-width: 150px;">
                            <option value="">Wybierz klasę...</option>
                            <?php foreach ($klasy as $klasa): ?>
                                <option value="<?php echo $klasa['id']; ?>" <?php echo $klasa_id == $klasa['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($klasa['nazwa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="date" id="data-od" value="<?php echo $data_od; ?>" class="form-control">
                        <input type="date" id="data-do" value="<?php echo $data_do; ?>" class="form-control">

                        <button id="zaladuj-plan" class="btn btn-primary">Załaduj Plan</button>
                    </div>

                    <div class="editor-actions">
                        <button id="cofnij-zmiane" class="btn btn-secondary" title="Cofnij ostatnią zmianę">
                            ↶ Cofnij
                        </button>
                        <button id="sprawdz-konflikty-btn" class="btn btn-warning">
                            ⚠ Sprawdź Konflikty
                        </button>
                    </div>
                </div>

                <!-- Alerty -->
                <div id="alert-container"></div>

                <!-- Loader -->
                <div id="loader" class="loader"></div>

                <!-- Tabela planu -->
                <div id="plan-container">
                    <p class="text-center" style="color: #999;">Wybierz klasę i okres, a następnie kliknij "Załaduj Plan"</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal edycji/dodawania lekcji -->
    <div id="lekcja-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Edytuj Lekcję</h3>
                <span class="close">&times;</span>
            </div>

            <form id="lekcja-form">
                <input type="hidden" id="form-plan-id">
                <input type="hidden" id="form-klasa-id">
                <input type="hidden" id="form-data">
                <input type="hidden" id="form-numer-lekcji">

                <div class="form-group">
                    <label for="form-przedmiot">Przedmiot:</label>
                    <select id="form-przedmiot" required>
                        <option value="">Wybierz przedmiot...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="form-nauczyciel">Nauczyciel:</label>
                    <select id="form-nauczyciel" required>
                        <option value="">Wybierz nauczyciela...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="form-sala">Sala:</label>
                    <select id="form-sala">
                        <option value="">Brak sali</option>
                    </select>
                </div>

                <div id="modal-alert-container"></div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Zapisz</button>
                    <button type="button" id="sprawdz-konflikty-modal" class="btn btn-warning">Sprawdź Konflikty</button>
                    <button type="button" class="close-modal btn btn-secondary">Anuluj</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/plan_edytor.js"></script>
</body>
</html>
