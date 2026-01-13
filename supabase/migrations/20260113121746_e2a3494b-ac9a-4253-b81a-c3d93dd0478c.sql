-- Add sentiment analysis columns to feedback table
ALTER TABLE public.feedback 
ADD COLUMN sentiment TEXT CHECK (sentiment IN ('positive', 'negative', 'neutral')),
ADD COLUMN sentiment_score NUMERIC;