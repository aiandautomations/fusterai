<?php

use App\Enums\ChannelType;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\ThreadType;

// ── ConversationStatus ───────────────────────────────────────────────────────

test('ConversationStatus has correct values', function () {
    expect(ConversationStatus::Open->value)->toBe('open');
    expect(ConversationStatus::Pending->value)->toBe('pending');
    expect(ConversationStatus::Closed->value)->toBe('closed');
    expect(ConversationStatus::Spam->value)->toBe('spam');
});

test('ConversationStatus labels are human readable', function () {
    expect(ConversationStatus::Open->label())->toBe('Open');
    expect(ConversationStatus::Pending->label())->toBe('Pending');
    expect(ConversationStatus::Closed->label())->toBe('Closed');
    expect(ConversationStatus::Spam->label())->toBe('Spam');
});

test('ConversationStatus can be created from valid string', function () {
    expect(ConversationStatus::from('open'))->toBe(ConversationStatus::Open);
    expect(ConversationStatus::from('closed'))->toBe(ConversationStatus::Closed);
});

test('ConversationStatus tryFrom returns null for invalid value', function () {
    expect(ConversationStatus::tryFrom('invalid'))->toBeNull();
    expect(ConversationStatus::tryFrom('snoozed'))->toBeNull();
    expect(ConversationStatus::tryFrom(''))->toBeNull();
});

// ── ConversationPriority ─────────────────────────────────────────────────────

test('ConversationPriority has correct values', function () {
    expect(ConversationPriority::Low->value)->toBe('low');
    expect(ConversationPriority::Normal->value)->toBe('normal');
    expect(ConversationPriority::High->value)->toBe('high');
    expect(ConversationPriority::Urgent->value)->toBe('urgent');
});

test('ConversationPriority labels are human readable', function () {
    expect(ConversationPriority::Low->label())->toBe('Low');
    expect(ConversationPriority::Normal->label())->toBe('Normal');
    expect(ConversationPriority::High->label())->toBe('High');
    expect(ConversationPriority::Urgent->label())->toBe('Urgent');
});

test('ConversationPriority tryFrom returns null for invalid value', function () {
    expect(ConversationPriority::tryFrom('critical'))->toBeNull();
    expect(ConversationPriority::tryFrom('medium'))->toBeNull();
});

// ── ThreadType ───────────────────────────────────────────────────────────────

test('ThreadType has correct values', function () {
    expect(ThreadType::Message->value)->toBe('message');
    expect(ThreadType::Note->value)->toBe('note');
    expect(ThreadType::Activity->value)->toBe('activity');
    expect(ThreadType::AiSuggestion->value)->toBe('ai_suggestion');
});

test('ThreadType labels are human readable', function () {
    expect(ThreadType::Message->label())->toBe('Message');
    expect(ThreadType::Note->label())->toBe('Note');
    expect(ThreadType::Activity->label())->toBe('Activity');
    expect(ThreadType::AiSuggestion->label())->toBe('AI Suggestion');
});

test('ThreadType tryFrom returns null for invalid value', function () {
    expect(ThreadType::tryFrom('reply'))->toBeNull();
    expect(ThreadType::tryFrom('draft'))->toBeNull();
});

// ── ChannelType ──────────────────────────────────────────────────────────────

test('ChannelType has correct values', function () {
    expect(ChannelType::Email->value)->toBe('email');
    expect(ChannelType::Chat->value)->toBe('chat');
    expect(ChannelType::WhatsApp->value)->toBe('whatsapp');
    expect(ChannelType::Slack->value)->toBe('slack');
    expect(ChannelType::Api->value)->toBe('api');
    expect(ChannelType::Sms->value)->toBe('sms');
});

test('ChannelType labels are human readable', function () {
    expect(ChannelType::Email->label())->toBe('Email');
    expect(ChannelType::Chat->label())->toBe('Live Chat');
    expect(ChannelType::WhatsApp->label())->toBe('WhatsApp');
    expect(ChannelType::Slack->label())->toBe('Slack');
    expect(ChannelType::Api->label())->toBe('API');
    expect(ChannelType::Sms->label())->toBe('SMS');
});
