# Example Skill Plugin

Template ini bisa dijadikan titik awal untuk menambah skill chatbot baru lewat sistem plugin.

## Cara Clone Cepat

1. Copy folder `plugins/example-skill-plugin/` ke slug baru, misalnya `plugins/faq-delivery/`.
2. Ganti nama file/class berikut:
   - `ExampleFaqSkill.php` -> misalnya `FaqDeliverySkill.php`
   - `ExampleFaqSkillPlugin.php` -> misalnya `FaqDeliverySkillPlugin.php`
   - update `plugin.php` agar menunjuk ke class plugin baru
3. Ubah isi skill:
   - intent di `canHandle()`
   - keyword intent di `registerIntentPatterns()`
   - reply di `handle()`

## File Penting

- `plugin.php`
  Entry point plugin dan metadata plugin.
- `ExampleFaqSkill.php`
  Skill chatbot yang menangani satu intent.
- `ExampleFaqSkillPlugin.php`
  Tempat registrasi skill dan keyword intent.

## Pola Registrasi

### Register skill

```php
return SkillRegistry::register($skills, new FaqDeliverySkill(), 70);
```

- Angka prioritas lebih kecil = skill dijalankan lebih dulu.
- `RefusalSkill` bawaan ada di prioritas `999`.

### Register keyword intent

```php
return IntentPatternRegistry::extend($patterns, 'faq_delivery', [
    'delivery',
    'pengiriman',
    'antar',
]);
```

## Checklist Setelah Clone

- Pastikan `canHandle()` memakai intent yang sama dengan `registerIntentPatterns()`.
- Pastikan `plugin.php` me-`require_once` file/class yang benar.
- Aktifkan plugin di `plugins/plugins.json`.
- Test dengan chat yang mengandung keyword intent baru.

## Catatan

- Kalau proyek memakai LLM untuk intent detection, keyword ini tetap berguna sebagai fallback rule-based.
- Skill bisa memakai `conv_context` untuk menyimpan state ringan antar pesan.
