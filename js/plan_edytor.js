/**
 * Edytor Planu Lekcji - JavaScript
 *
 * Funkcjonalności:
 * - Drag & Drop przesuwanie lekcji
 * - Dodawanie nowych lekcji
 * - Edycja istniejących lekcji
 * - Usuwanie lekcji
 * - Sprawdzanie konfliktów
 * - Cofanie zmian (undo)
 */

class PlanEdytor {
    constructor() {
        console.log('PlanEdytor: Inicjalizacja...');
        this.apiUrl = 'plan_edycja.php';
        this.currentKlasaId = null;
        this.currentDataOd = null;
        this.currentDataDo = null;
        this.daneFormularza = null;
        this.draggedElement = null;

        this.init();
    }

    init() {
        console.log('PlanEdytor: init()');
        // Pobierz dane do formularza (klasy, przedmioty, etc.)
        this.zaladujDaneFormularza();

        // Eventy dla kontrolek
        const zaladujBtn = document.getElementById('zaladuj-plan');
        const cofnijBtn = document.getElementById('cofnij-zmiane');
        const konfliktBtn = document.getElementById('sprawdz-konflikty-btn');

        console.log('Elementy DOM:', { zaladujBtn, cofnijBtn, konfliktBtn });

        if (zaladujBtn) {
            zaladujBtn.addEventListener('click', () => {
                console.log('Kliknięto przycisk Załaduj Plan');
                this.zaladujPlan();
            });
        } else {
            console.error('Nie znaleziono przycisku zaladuj-plan');
        }

        if (cofnijBtn) {
            cofnijBtn.addEventListener('click', () => this.cofnijZmiane());
        }

        if (konfliktBtn) {
            konfliktBtn.addEventListener('click', () => this.sprawdzWszystkieKonflikty());
        }

        // Modal
        const modal = document.getElementById('lekcja-modal');
        const closeButtons = document.querySelectorAll('.close, .close-modal');

        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Formularz
        document.getElementById('lekcja-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.zapiszLekcje();
        });

        document.getElementById('sprawdz-konflikty-modal').addEventListener('click', () => {
            this.sprawdzKonfliktyModal();
        });
    }

    async zaladujDaneFormularza() {
        try {
            console.log('Ładowanie danych formularza...');
            const response = await fetch(`${this.apiUrl}?action=pobierz_dane_formularza`);
            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Dane formularza:', data);

            if (data.success) {
                this.daneFormularza = data;
                this.wypelnijSelecty();
            } else {
                console.error('Błąd API:', data);
            }
        } catch (error) {
            console.error('Błąd podczas ładowania danych formularza:', error);
        }
    }

    wypelnijSelecty() {
        const przedmiotySelect = document.getElementById('form-przedmiot');
        const nauczycieleSelect = document.getElementById('form-nauczyciel');
        const saleSelect = document.getElementById('form-sala');

        // Przedmioty
        przedmiotySelect.innerHTML = '<option value="">Wybierz przedmiot...</option>';
        this.daneFormularza.przedmioty.forEach(p => {
            przedmiotySelect.innerHTML += `<option value="${p.id}">${p.nazwa}</option>`;
        });

        // Nauczyciele
        nauczycieleSelect.innerHTML = '<option value="">Wybierz nauczyciela...</option>';
        this.daneFormularza.nauczyciele.forEach(n => {
            nauczycieleSelect.innerHTML += `<option value="${n.id}">${n.nazwa}</option>`;
        });

        // Sale
        saleSelect.innerHTML = '<option value="">Brak sali</option>';
        this.daneFormularza.sale.forEach(s => {
            const nazwa = s.nazwa ? ` - ${s.nazwa}` : '';
            saleSelect.innerHTML += `<option value="${s.id}">Sala ${s.numer}${nazwa}</option>`;
        });
    }

    async zaladujPlan() {
        console.log('zaladujPlan() wywołane');
        const klasaId = document.getElementById('klasa-select').value;
        const dataOd = document.getElementById('data-od').value;
        const dataDo = document.getElementById('data-do').value;

        console.log('Parametry:', { klasaId, dataOd, dataDo });

        if (!klasaId || !dataOd || !dataDo) {
            this.pokazAlert('Wybierz klasę i okres', 'error');
            return;
        }

        this.currentKlasaId = klasaId;
        this.currentDataOd = dataOd;
        this.currentDataDo = dataDo;

        this.pokazLoader(true);

        try {
            const url = `${this.apiUrl}?action=pobierz_plan&klasa_id=${klasaId}&data_od=${dataOd}&data_do=${dataDo}`;
            console.log('Fetching URL:', url);

            const response = await fetch(url);
            console.log('Response status:', response.status);

            const data = await response.json();
            console.log('Response data:', data);

            if (data.success) {
                console.log('Renderowanie planu z', data.plan.length, 'lekcjami');
                this.renderujPlan(data.plan);
            } else {
                console.error('Błąd API:', data.message);
                this.pokazAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Błąd podczas ładowania planu:', error);
            this.pokazAlert('Błąd podczas ładowania planu: ' + error.message, 'error');
        } finally {
            this.pokazLoader(false);
        }
    }

    renderujPlan(lekcje) {
        // Przygotuj strukturę danych (dni × lekcje)
        const dni = this.pobierzDniWPeriodzie(this.currentDataOd, this.currentDataDo);
        const maxLekcji = 10;

        // Utwórz mapę lekcji [data][numer_lekcji] => lekcja
        const mapaLekcji = {};
        lekcje.forEach(lekcja => {
            if (!mapaLekcji[lekcja.data]) {
                mapaLekcji[lekcja.data] = {};
            }
            mapaLekcji[lekcja.data][lekcja.numer_lekcji] = lekcja;
        });

        // Generuj HTML tabeli
        let html = '<table class="schedule-editor">';

        // Nagłówek
        html += '<thead><tr><th class="time-column">Lekcja</th>';
        dni.forEach(dzien => {
            const dzienNazwa = this.formatujDate(dzien.data);
            html += `<th>${dzien.nazwa}<br><small>${dzienNazwa}</small></th>`;
        });
        html += '</tr></thead>';

        // Wiersze lekcji
        html += '<tbody>';
        for (let numerLekcji = 1; numerLekcji <= maxLekcji; numerLekcji++) {
            const godziny = this.obliczGodzinyLekcji(numerLekcji);
            html += `<tr>`;
            html += `<td class="time-column">${numerLekcji}<br><small>${godziny}</small></td>`;

            dni.forEach(dzien => {
                const lekcja = mapaLekcji[dzien.data]?.[numerLekcji];

                if (lekcja) {
                    // Komórka z lekcją
                    html += `
                        <td class="lesson-cell"
                            draggable="true"
                            data-plan-id="${lekcja.id}"
                            data-klasa-id="${lekcja.klasa_id}"
                            data-data="${lekcja.data}"
                            data-numer-lekcji="${lekcja.numer_lekcji}"
                            data-przedmiot-id="${lekcja.przedmiot_id}"
                            data-nauczyciel-id="${lekcja.nauczyciel_id}"
                            data-sala-id="${lekcja.sala_id || ''}">

                            <div class="lesson-content">
                                <span class="przedmiot">${lekcja.przedmiot_skrot || lekcja.przedmiot_nazwa}</span>
                                <span class="nauczyciel">${lekcja.nauczyciel_nazwa}</span>
                                <span class="sala">${lekcja.sala_numer ? 'Sala ' + lekcja.sala_numer : ''}</span>
                            </div>

                            <div class="lesson-actions">
                                <button class="edit-btn" title="Edytuj" onclick="edytor.otworzModalEdycji(${lekcja.id})">✏️</button>
                                <button class="delete-btn" title="Usuń" onclick="edytor.usunLekcje(${lekcja.id})">🗑️</button>
                            </div>
                        </td>
                    `;
                } else {
                    // Pusta komórka (możliwość dodania lekcji)
                    html += `
                        <td class="lesson-cell empty"
                            data-klasa-id="${this.currentKlasaId}"
                            data-data="${dzien.data}"
                            data-numer-lekcji="${numerLekcji}"
                            onclick="edytor.otworzModalDodawania(${this.currentKlasaId}, '${dzien.data}', ${numerLekcji})">
                            <div class="lesson-content">
                                <small style="color: #999;">+ Dodaj lekcję</small>
                            </div>
                        </td>
                    `;
                }
            });

            html += '</tr>';
        }
        html += '</tbody></table>';

        document.getElementById('plan-container').innerHTML = html;

        // Dodaj eventy dla drag & drop
        this.inicjalizujDragDrop();
    }

    inicjalizujDragDrop() {
        const cells = document.querySelectorAll('.lesson-cell[draggable="true"]');

        cells.forEach(cell => {
            cell.addEventListener('dragstart', (e) => {
                this.draggedElement = cell;
                cell.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            cell.addEventListener('dragend', (e) => {
                cell.classList.remove('dragging');
                this.draggedElement = null;
            });
        });

        // Wszystkie komórki (włącznie z pustymi) mogą być targetem
        const allCells = document.querySelectorAll('.lesson-cell');

        allCells.forEach(cell => {
            cell.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                cell.classList.add('drag-over');
            });

            cell.addEventListener('dragleave', (e) => {
                cell.classList.remove('drag-over');
            });

            cell.addEventListener('drop', (e) => {
                e.preventDefault();
                cell.classList.remove('drag-over');

                if (!this.draggedElement || this.draggedElement === cell) {
                    return;
                }

                // Przenieś lekcję
                const planId = this.draggedElement.dataset.planId;
                const nowaData = cell.dataset.data;
                const nowyNumerLekcji = cell.dataset.numerLekcji;

                this.przeniesLekcje(planId, nowaData, nowyNumerLekcji);
            });
        });
    }

    async przeniesLekcje(planId, nowaData, nowyNumerLekcji) {
        if (confirm('Czy na pewno chcesz przenieść tę lekcję?')) {
            this.pokazLoader(true);

            try {
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'przenies_lekcje',
                        plan_dzienny_id: planId,
                        nowa_data: nowaData,
                        nowy_numer_lekcji: nowyNumerLekcji
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.pokazAlert('Lekcja została przeniesiona pomyślnie', 'success');
                    this.zaladujPlan();
                } else {
                    if (data.konflikty) {
                        this.pokazKonflikty(data.konflikty);
                    }
                    this.pokazAlert(data.message, 'error');
                }
            } catch (error) {
                this.pokazAlert('Błąd podczas przesuwania lekcji: ' + error.message, 'error');
            } finally {
                this.pokazLoader(false);
            }
        }
    }

    otworzModalDodawania(klasaId, data, numerLekcji) {
        const modal = document.getElementById('lekcja-modal');
        document.getElementById('modal-title').textContent = 'Dodaj Lekcję';
        document.getElementById('form-plan-id').value = '';
        document.getElementById('form-klasa-id').value = klasaId;
        document.getElementById('form-data').value = data;
        document.getElementById('form-numer-lekcji').value = numerLekcji;
        document.getElementById('form-przedmiot').value = '';
        document.getElementById('form-nauczyciel').value = '';
        document.getElementById('form-sala').value = '';
        document.getElementById('modal-alert-container').innerHTML = '';

        modal.style.display = 'block';
    }

    otworzModalEdycji(planId) {
        // Znajdź komórkę z lekcją
        const cell = document.querySelector(`[data-plan-id="${planId}"]`);

        if (!cell) {
            this.pokazAlert('Nie znaleziono lekcji', 'error');
            return;
        }

        const modal = document.getElementById('lekcja-modal');
        document.getElementById('modal-title').textContent = 'Edytuj Lekcję';
        document.getElementById('form-plan-id').value = planId;
        document.getElementById('form-klasa-id').value = cell.dataset.klasaId;
        document.getElementById('form-data').value = cell.dataset.data;
        document.getElementById('form-numer-lekcji').value = cell.dataset.numerLekcji;
        document.getElementById('form-przedmiot').value = cell.dataset.przedmiotId;
        document.getElementById('form-nauczyciel').value = cell.dataset.nauczycielId;
        document.getElementById('form-sala').value = cell.dataset.salaId || '';
        document.getElementById('modal-alert-container').innerHTML = '';

        modal.style.display = 'block';
    }

    async zapiszLekcje() {
        const planId = document.getElementById('form-plan-id').value;
        const klasaId = document.getElementById('form-klasa-id').value;
        const data = document.getElementById('form-data').value;
        const numerLekcji = document.getElementById('form-numer-lekcji').value;
        const przedmiotId = document.getElementById('form-przedmiot').value;
        const nauczycielId = document.getElementById('form-nauczyciel').value;
        const salaId = document.getElementById('form-sala').value || null;

        if (!przedmiotId || !nauczycielId) {
            this.pokazAlertModal('Wypełnij wszystkie wymagane pola', 'error');
            return;
        }

        this.pokazLoader(true);

        try {
            let action, body;

            if (planId) {
                // Edycja
                action = 'edytuj_lekcje';
                body = {
                    action,
                    plan_dzienny_id: planId,
                    przedmiot_id: przedmiotId,
                    nauczyciel_id: nauczycielId,
                    sala_id: salaId
                };
            } else {
                // Dodawanie
                action = 'dodaj_lekcje';
                body = {
                    action,
                    klasa_id: klasaId,
                    data: data,
                    numer_lekcji: numerLekcji,
                    przedmiot_id: przedmiotId,
                    nauczyciel_id: nauczycielId,
                    sala_id: salaId
                };
            }

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });

            const data_response = await response.json();

            if (data_response.success) {
                this.pokazAlert(data_response.message, 'success');
                document.getElementById('lekcja-modal').style.display = 'none';
                this.zaladujPlan();
            } else {
                if (data_response.konflikty) {
                    this.pokazKonflyktyModal(data_response.konflikty);
                }
                this.pokazAlertModal(data_response.message, 'error');
            }
        } catch (error) {
            this.pokazAlertModal('Błąd podczas zapisywania: ' + error.message, 'error');
        } finally {
            this.pokazLoader(false);
        }
    }

    async usunLekcje(planId) {
        if (!confirm('Czy na pewno chcesz usunąć tę lekcję?')) {
            return;
        }

        this.pokazLoader(true);

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'usun_lekcje',
                    plan_dzienny_id: planId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.pokazAlert('Lekcja została usunięta', 'success');
                this.zaladujPlan();
            } else {
                this.pokazAlert(data.message, 'error');
            }
        } catch (error) {
            this.pokazAlert('Błąd podczas usuwania: ' + error.message, 'error');
        } finally {
            this.pokazLoader(false);
        }
    }

    async sprawdzKonfliktyModal() {
        const klasaId = document.getElementById('form-klasa-id').value;
        const data = document.getElementById('form-data').value;
        const numerLekcji = document.getElementById('form-numer-lekcji').value;
        const przedmiotId = document.getElementById('form-przedmiot').value;
        const nauczycielId = document.getElementById('form-nauczyciel').value;
        const salaId = document.getElementById('form-sala').value || null;
        const excludeId = document.getElementById('form-plan-id').value || null;

        if (!przedmiotId || !nauczycielId) {
            this.pokazAlertModal('Wypełnij przedmiot i nauczyciela', 'warning');
            return;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'sprawdz_konflikty',
                    klasa_id: klasaId,
                    data: data,
                    numer_lekcji: numerLekcji,
                    przedmiot_id: przedmiotId,
                    nauczyciel_id: nauczycielId,
                    sala_id: salaId,
                    exclude_id: excludeId
                })
            });

            const konflikty = await response.json();

            if (konflikty.ma_konflikty) {
                this.pokazKonflyktyModal(konflikty.lista);
            } else {
                this.pokazAlertModal('Brak konfliktów! Możesz zapisać lekcję.', 'success');
            }
        } catch (error) {
            this.pokazAlertModal('Błąd podczas sprawdzania konfliktów: ' + error.message, 'error');
        }
    }

    async sprawdzWszystkieKonflikty() {
        this.pokazLoader(true);

        try {
            const response = await fetch(`${this.apiUrl}?action=pobierz_konflikty&tylko_nierozwiazane=1`);
            const data = await response.json();

            if (data.success) {
                if (data.konflikty.length === 0) {
                    this.pokazAlert('Brak wykrytych konfliktów!', 'success');
                } else {
                    this.pokazKonflikty(data.konflikty);
                    this.pokazAlert(`Wykryto ${data.konflikty.length} konfliktów`, 'warning');
                }
            }
        } catch (error) {
            this.pokazAlert('Błąd podczas sprawdzania konfliktów: ' + error.message, 'error');
        } finally {
            this.pokazLoader(false);
        }
    }

    async cofnijZmiane() {
        if (!confirm('Czy na pewno chcesz cofnąć ostatnią zmianę?')) {
            return;
        }

        this.pokazLoader(true);

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'cofnij_zmiane'
                })
            });

            const data = await response.json();

            if (data.success) {
                this.pokazAlert('Zmiana została cofnięta', 'success');
                this.zaladujPlan();
            } else {
                this.pokazAlert(data.message, 'error');
            }
        } catch (error) {
            this.pokazAlert('Błąd podczas cofania zmiany: ' + error.message, 'error');
        } finally {
            this.pokazLoader(false);
        }
    }

    pokazKonflikty(konflikty) {
        let html = '<div class="alert alert-warning"><strong>Wykryte konflikty:</strong><div class="conflicts-list">';

        konflikty.forEach(konflikt => {
            html += `
                <div class="conflict-item conflict-${konflikt.typ}">
                    <strong>${this.tlumaczTypKonfliktu(konflikt.typ)}</strong>
                    <p>${konflikt.opis}</p>
                </div>
            `;
        });

        html += '</div></div>';

        this.pokazAlert(html, '', true);
    }

    pokazKonflyktyModal(konflikty) {
        let html = '<div class="alert alert-warning"><strong>Wykryte konflikty:</strong><div class="conflicts-list">';

        konflikty.forEach(konflikt => {
            html += `
                <div class="conflict-item conflict-${konflikt.typ}">
                    <strong>${this.tlumaczTypKonfliktu(konflikt.typ)}</strong>
                    <p>${konflikt.opis}</p>
                </div>
            `;
        });

        html += '</div></div>';

        document.getElementById('modal-alert-container').innerHTML = html;
    }

    tlumaczTypKonfliktu(typ) {
        const tlumaczenia = {
            'nauczyciel': 'Konflikt Nauczyciela',
            'sala': 'Konflikt Sali',
            'klasa': 'Konflikt Klasy',
            'dostepnosc': 'Brak Dostępności',
            'wymiar_godzin': 'Przekroczony Wymiar Godzin'
        };
        return tlumaczenia[typ] || typ;
    }

    pokazAlert(message, type = 'info', raw = false) {
        console.log('pokazAlert:', message, type);
        const container = document.getElementById('alert-container');

        if (!container) {
            console.error('Nie znaleziono alert-container');
            return;
        }

        const alertClass = type === 'success' ? 'alert-success' :
                          type === 'error' ? 'alert-error' :
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        if (raw) {
            container.innerHTML = message;
        } else {
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
        }

        // Automatycznie ukryj po 5 sekundach
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }

    pokazAlertModal(message, type = 'info') {
        const container = document.getElementById('modal-alert-container');
        const alertClass = type === 'success' ? 'alert-success' :
                          type === 'error' ? 'alert-error' :
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
    }

    pokazLoader(show) {
        document.getElementById('loader').style.display = show ? 'block' : 'none';
    }

    pobierzDniWPeriodzie(dataOd, dataDo) {
        const dni = [];
        const current = new Date(dataOd);
        const end = new Date(dataDo);

        const nazwaDni = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];

        while (current <= end) {
            const dayOfWeek = current.getDay();
            // Tylko dni robocze (1-5)
            if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                dni.push({
                    data: this.formatujDataISO(current),
                    nazwa: nazwaDni[dayOfWeek]
                });
            }
            current.setDate(current.getDate() + 1);
        }

        return dni;
    }

    formatujDataISO(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    formatujDate(dataISO) {
        const parts = dataISO.split('-');
        return `${parts[2]}.${parts[1]}`;
    }

    obliczGodzinyLekcji(numerLekcji) {
        // Uproszczone obliczenie (domyślne wartości)
        const godzinaStart = 8 * 60; // 08:00 w minutach
        const dlugoscLekcji = 45;
        const przerwa = 10;
        const dlugaPrzerwa = 15;

        let poczatek = godzinaStart;

        for (let i = 1; i < numerLekcji; i++) {
            poczatek += dlugoscLekcji;
            poczatek += (i === 3) ? dlugaPrzerwa : przerwa;
        }

        const koniec = poczatek + dlugoscLekcji;

        return this.minutyNaCzas(poczatek) + '-' + this.minutyNaCzas(koniec);
    }

    minutyNaCzas(minuty) {
        const h = Math.floor(minuty / 60);
        const m = minuty % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
}

// Inicjalizacja
const edytor = new PlanEdytor();
