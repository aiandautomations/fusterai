import React from 'react';

interface SurveyData {
    rating: 'good' | 'bad';
    responded_at: string;
}

interface Props {
    survey?: SurveyData | null;
    conversationStatus?: string;
}

export default function SurveySidebarPanel({ survey, conversationStatus }: Props) {
    if (!survey && conversationStatus !== 'closed') return null;

    return (
        <div className="p-4 border-b border-border">
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">CSAT</p>

            {survey ? (
                <div className="flex items-center gap-2">
                    <span className={`text-xl ${survey.rating === 'good' ? '' : ''}`}>{survey.rating === 'good' ? '👍' : '👎'}</span>
                    <div>
                        <p className={`text-sm font-medium ${survey.rating === 'good' ? 'text-green-700' : 'text-red-700'}`}>
                            {survey.rating === 'good' ? 'Good' : 'Bad'}
                        </p>
                        <p className="text-xs text-muted-foreground">{new Date(survey.responded_at).toLocaleDateString()}</p>
                    </div>
                </div>
            ) : (
                <p className="text-xs text-muted-foreground italic">Survey sent — awaiting response</p>
            )}
        </div>
    );
}

/*
 * ── How to register this component in the frontend ───────────────────────────
 *
 * In resources/js/app.tsx (or a module bootstrap file), import and register:
 *
 *   import SurveySidebarPanel from '@/../../Modules/SatisfactionSurvey/Resources/js/SurveySidebarPanel';
 *   import { registerSlot } from '@/Components/SlotRenderer';
 *   registerSlot('conversation.sidebar.bottom', SurveySidebarPanel);
 *
 * Then in Conversations/Show.tsx sidebar:
 *   <SlotRenderer name="conversation.sidebar.bottom" props={{ survey, conversationStatus: conversation.status }} />
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */
