// Akışla talep formu: istemci doğrulaması + gönderim durumları + ön görüşme seçimi.
// İstemci doğrulaması yalnızca kullanıcı deneyimi içindir; asıl kontrol api/submit.php'de.

(function () {
  'use strict';

  var form = document.getElementById('request-form');
  var button = document.getElementById('submit-btn');
  var statusBox = document.getElementById('form-status');
  var messageInput = document.getElementById('message');
  var messageCount = document.getElementById('message-count');

  var meetingBox = document.getElementById('meeting');
  var daysBox = document.getElementById('meeting-days');
  var slotsWrap = document.getElementById('meeting-slots-wrap');
  var slotsBox = document.getElementById('meeting-slots');
  var slotsStatus = document.getElementById('meeting-slots-status');
  var clearButton = document.getElementById('meeting-clear');

  var SERVICES = ['otomasyon-kurulumu', 'entegrasyon', 'raporlama', 'danismanlik'];
  var FIELDS = ['name', 'email', 'service', 'message'];
  var MEETING_DAYS = 10; // yarından itibaren gösterilecek iş günü sayısı (sunucu sınırı: 14 takvim günü)
  var BUTTON_TEXT = button.textContent;
  var TIMEOUT_MS = 15000;
  var submitting = false;
  var slotsRequest = 0; // eski "saatler" yanıtlarını yok saymak için sayaç

  // Karakter sayısı: Array.from kod noktası sayar (PHP mb_strlen ile aynı sonuç).
  function len(value) {
    return Array.from(value).length;
  }

  // Sunucudaki validate_request() ile aynı kurallar.
  var rules = {
    name: function (v) {
      if (len(v) < 2 || len(v) > 100) return 'İsim 2–100 karakter olmalı.';
      return '';
    },
    email: function (v) {
      if (v === '') return 'E-posta zorunlu.';
      if (v.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Geçerli bir e-posta adresi girin.';
      return '';
    },
    service: function (v) {
      return SERVICES.indexOf(v) === -1 ? 'Listeden bir hizmet seçin.' : '';
    },
    message: function (v) {
      if (len(v) < 10 || len(v) > 2000) return 'Açıklama 10–2000 karakter olmalı.';
      return '';
    }
  };

  function value(name) {
    return form.elements[name].value.trim();
  }

  // Seçili radio değeri ('' = seçim yok). Radio grubu henüz oluşmadıysa da ''.
  function radioValue(name) {
    var group = form.elements[name];
    return group ? group.value : '';
  }

  function meetingError() {
    var date = radioValue('meeting_date');
    var slot = radioValue('meeting_slot');
    if (date && !slot) return 'Bir saat seçin ya da randevu seçimini kaldırın.';
    return '';
  }

  function setFieldError(name, message) {
    document.getElementById(name + '-error').textContent = message;
    if (name === 'meeting') {
      meetingBox.classList.toggle('is-invalid', !!message);
      return;
    }
    var input = form.elements[name];
    if (message) {
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.removeAttribute('aria-invalid');
    }
  }

  function validateField(name) {
    var message = rules[name](value(name));
    setFieldError(name, message);
    return message === '';
  }

  // Hata alınca odaklanılacak eleman (randevuda seçili gün ya da ilk gün).
  function focusTarget(name) {
    if (name !== 'meeting') return form.elements[name];
    return slotsBox.querySelector('input:not(:disabled)') ||
      daysBox.querySelector('input:checked') || daysBox.querySelector('input');
  }

  // Hatalı alanları işaretler, ilk hatalı alana odaklanır. Hata yoksa true.
  function showErrors(errors) {
    var first = null;
    FIELDS.concat('meeting').forEach(function (name) {
      setFieldError(name, errors[name] || '');
      if (errors[name] && !first) first = name;
    });
    if (first) {
      var target = focusTarget(first);
      if (target) target.focus();
    }
    return first === null;
  }

  function setStatus(type, message) {
    statusBox.className = 'form-status' + (type ? ' is-' + type : '');
    statusBox.textContent = message;
  }

  function setSubmitting(on) {
    submitting = on;
    button.disabled = on;
    button.textContent = on ? 'Gönderiliyor…' : BUTTON_TEXT;
    form.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  // ---------- Ön görüşme: gün ve saat seçimi ----------

  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function isoDate(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  // <label class="chip"><input type="radio"><span>…</span></label>
  function chip(name, val, mainText, subText, disabled) {
    var label = document.createElement('label');
    label.className = 'chip';
    var input = document.createElement('input');
    input.type = 'radio';
    input.name = name;
    input.value = val;
    input.disabled = !!disabled;
    var span = document.createElement('span');
    span.textContent = mainText;
    if (subText) {
      var small = document.createElement('small');
      small.textContent = subText;
      span.appendChild(small);
    }
    label.appendChild(input);
    label.appendChild(span);
    return label;
  }

  // Yarından başlayarak hafta içi günleri listeler.
  function renderDays() {
    var d = new Date();
    var added = 0;
    while (added < MEETING_DAYS) {
      d.setDate(d.getDate() + 1);
      var weekday = d.getDay();
      if (weekday === 0 || weekday === 6) continue;
      daysBox.appendChild(chip(
        'meeting_date',
        isoDate(d),
        d.toLocaleDateString('tr-TR', { weekday: 'short' }),
        d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
      ));
      added++;
    }
  }

  function resetMeeting() {
    slotsRequest++;
    slotsBox.innerHTML = '';
    slotsWrap.hidden = true;
    slotsStatus.textContent = '';
    clearButton.hidden = true;
    daysBox.querySelectorAll('input').forEach(function (input) { input.checked = false; });
    setFieldError('meeting', '');
  }

  // Seçilen gün için dolu/boş saatleri sunucudan alır.
  function loadSlots(date) {
    var requestNo = ++slotsRequest;
    slotsBox.innerHTML = '';
    slotsWrap.hidden = true;
    slotsStatus.textContent = 'Uygun saatler yükleniyor…';

    fetch('api/slots.php?date=' + encodeURIComponent(date), { headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.json(); })
      .then(function (body) {
        if (requestNo !== slotsRequest) return; // bu arada başka gün seçildi
        if (!body || body.ok !== true) throw new Error('slots');

        var free = 0;
        body.slots.forEach(function (slot) {
          if (slot.available) free++;
          slotsBox.appendChild(chip('meeting_slot', slot.time, slot.time, slot.available ? '' : 'dolu', !slot.available));
        });
        slotsWrap.hidden = false;
        slotsStatus.textContent = free === 0
          ? 'Bu günde boş saat kalmadı, başka bir gün seçin.'
          : free + ' boş saat var.';
      })
      .catch(function () {
        if (requestNo !== slotsRequest) return;
        slotsStatus.textContent = 'Uygun saatler alınamadı. Talebinizi randevusuz da gönderebilirsiniz.';
      });
  }

  daysBox.addEventListener('change', function (event) {
    clearButton.hidden = false;
    setFieldError('meeting', '');
    loadSlots(event.target.value);
  });

  slotsBox.addEventListener('change', function () {
    setFieldError('meeting', '');
  });

  clearButton.addEventListener('click', function () {
    resetMeeting();
    var first = daysBox.querySelector('input');
    if (first) first.focus();
  });

  renderDays();

  // ---------- Alan doğrulaması ve gönderim ----------

  // Alan bırakıldığında doğrula; hatalı alan düzeltilirken hatayı anında kaldır.
  FIELDS.forEach(function (name) {
    var input = form.elements[name];
    input.addEventListener('blur', function () {
      if (value(name) !== '') validateField(name);
    });
    input.addEventListener('input', function () {
      if (input.getAttribute('aria-invalid') === 'true') validateField(name);
    });
  });

  messageInput.addEventListener('input', function () {
    messageCount.textContent = len(messageInput.value);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (submitting) return; // çift gönderim engeli

    var errors = {};
    FIELDS.forEach(function (name) {
      var message = rules[name](value(name));
      if (message) errors[name] = message;
    });
    if (meetingError()) errors.meeting = meetingError();
    if (!showErrors(errors)) {
      setStatus('error', 'Lütfen işaretli alanları düzeltin.');
      return;
    }

    var payload = {
      name: value('name'),
      email: value('email'),
      service: value('service'),
      message: value('message'),
      meeting_date: radioValue('meeting_date'),
      meeting_slot: radioValue('meeting_slot'),
      website: form.elements.website.value
    };

    setSubmitting(true);
    setStatus('pending', 'Talebiniz gönderiliyor…');

    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, TIMEOUT_MS);

    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal
    })
      .then(function (response) {
        // Yanıt JSON değilse (ör. sunucu HTML hata sayfası döndü) body null kalır.
        return response.json()
          .catch(function () { return null; })
          .then(function (body) { return { status: response.status, body: body }; });
      })
      .then(function (result) {
        var body = result.body || {};

        // Başarı yalnızca sunucu kaydı gerçekten oluşturduğunda (201 + ok:true).
        if (result.status === 201 && body.ok === true) {
          form.reset();
          resetMeeting();
          messageCount.textContent = '0';
          showErrors({});
          var when = payload.meeting_date ? ' Ön görüşme: ' + payload.meeting_date + ' ' + payload.meeting_slot + '.' : '';
          setStatus('success', (body.message || 'Talebiniz alındı.') + when);
          statusBox.focus();
          return;
        }

        if ((result.status === 422 || result.status === 409) && body.errors) {
          showErrors(body.errors);
        }
        // Saat bu arada dolduysa listeyi yenile; dolu saat artık seçilemez görünür.
        if (result.status === 409 && payload.meeting_date) {
          loadSlots(payload.meeting_date);
        }
        setStatus('error', body.message || 'Talebiniz kaydedilemedi. Lütfen tekrar deneyin.');
        if (result.status !== 422 && result.status !== 409) statusBox.focus();
      })
      .catch(function () {
        setStatus('error', 'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.');
        statusBox.focus();
      })
      .finally(function () {
        clearTimeout(timer);
        setSubmitting(false);
      });
  });
})();
