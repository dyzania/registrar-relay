-- Add disabled_services column to windows table
ALTER TABLE public.windows 
ADD COLUMN disabled_services text[] DEFAULT '{}';

-- Update RLS to allow updating disabled_services
-- (existing update policy already covers this)