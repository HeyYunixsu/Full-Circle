<?php

if (($event['status'] ?? '') === 'archived') {
    return;
}

$can_end_event = in_array($_SESSION['role'] ?? '', ['admin', 'event_manager', 'super_admin']);
if (!$can_end_event) {
    return;
}

$event_id   = (int)$event['id'];
$event_name = htmlspecialchars($event['event_name']);
$is_completed = ($event['status'] ?? '') === 'completed';
?>

<button type="button"
        class="btn-end-event <?= $is_completed ? 'btn-end-event--archive' : '' ?>"
        onclick="openEndEventModal_<?= $event_id ?>()">
    <?= icon($is_completed ? 'shield' : 'check-circle', ['class' => 'icon-svg icon-sm']) ?>
    <span><?= $is_completed ? 'Archive Event' : 'End Event' ?></span>
</button>

<!-- Confirmation Modal -->
<div class="end-event-modal" id="endEventModal_<?= $event_id ?>" onclick="if(event.target===this) closeEndEventModal_<?= $event_id ?>()">
    <div class="end-event-modal__box">
        <div class="end-event-modal__icon">
            <?= icon($is_completed ? 'shield' : 'check-circle', ['class' => 'icon-svg icon-lg']) ?>
        </div>
        <h3 class="end-event-modal__title">
            <?= $is_completed ? 'Archive this event?' : 'End this event?' ?>
        </h3>
        <p class="end-event-modal__text">
            <strong><?= $event_name ?></strong><br>
            <?php if ($is_completed): ?>
                This event is already completed. Archiving moves it to the archived list and locks it permanently &mdash; the event and its attendee records become read-only and <strong>cannot be re-opened</strong>.
            <?php else: ?>
                The event status will be set to <strong>Completed</strong>. Any pending QR-code emails will be cancelled. This can be archived later.
            <?php endif; ?>
        </p>
        <div class="end-event-modal__actions">
            <button type="button" class="end-event-modal__btn end-event-modal__btn--cancel" onclick="closeEndEventModal_<?= $event_id ?>()">
                Cancel
            </button>
            <a href="<?= BASE_URL ?>/api/events/end.php?event_id=<?= $event_id ?><?= $is_completed ? '&archive=1' : '' ?>"
               class="end-event-modal__btn end-event-modal__btn--confirm">
                <?= $is_completed ? 'Archive Event' : 'Yes, End Event' ?>
            </a>
        </div>
    </div>
</div>

<style>

<?php if (!isset($GLOBALS['_end_event_btn_styles_loaded'])): $GLOBALS['_end_event_btn_styles_loaded'] = true; ?>
.btn-end-event {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}
.btn-end-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
}
.btn-end-event--archive {
    background: linear-gradient(135deg, #6b3d7a 0%, #4a2c5a 100%);
    box-shadow: 0 4px 12px rgba(107, 61, 122, 0.25);
}
.btn-end-event--archive:hover {
    box-shadow: 0 6px 16px rgba(107, 61, 122, 0.35);
}
.btn-end-event .icon-svg { color: #fff; }

.end-event-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(26, 10, 46, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.end-event-modal.show { display: flex; animation: ee-fade .2s ease; }
@keyframes ee-fade { from { opacity: 0; } to { opacity: 1; } }

.end-event-modal__box {
    background: #fff;
    border-radius: 20px;
    padding: 32px;
    max-width: 440px;
    width: 100%;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
    text-align: center;
    animation: ee-pop .25s ease;
}
@keyframes ee-pop {
    from { transform: scale(0.92); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

.end-event-modal__icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4a2c5a 0%, #d946ef 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 20px rgba(217, 70, 239, 0.3);
}
.end-event-modal__icon .icon-svg { color: #fff; width: 32px; height: 32px; }

.end-event-modal__title {
    font-size: 20px;
    font-weight: 700;
    color: #4a2c5a;
    margin: 0 0 12px;
    letter-spacing: -0.3px;
}
.end-event-modal__text {
    color: #4a3d52;
    font-size: 14px;
    line-height: 1.6;
    margin: 0 0 24px;
}
.end-event-modal__text strong { color: #4a2c5a; }

.end-event-modal__actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.end-event-modal__btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.end-event-modal__btn--cancel {
    background: #e8d5ed;
    color: #4a2c5a;
}
.end-event-modal__btn--cancel:hover { background: #f3d9f7; }
.end-event-modal__btn--confirm {
    background: linear-gradient(135deg, #4a2c5a 0%, #6b3d7a 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(107, 61, 122, 0.3);
}
.end-event-modal__btn--confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(107, 61, 122, 0.4);
}
<?php endif; ?>
</style>

<script>
function openEndEventModal_<?= $event_id ?>() {
    document.getElementById('endEventModal_<?= $event_id ?>').classList.add('show');
}
function closeEndEventModal_<?= $event_id ?>() {
    document.getElementById('endEventModal_<?= $event_id ?>').classList.remove('show');
}
</script>
