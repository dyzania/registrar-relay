-- Create enum for transaction types
CREATE TYPE public.transaction_type AS ENUM (
  'grade_request',
  'enrollment',
  'document_request',
  'payment',
  'clearance',
  'other'
);

-- Create enum for queue status
CREATE TYPE public.queue_status AS ENUM (
  'waiting',
  'in_progress',
  'completed',
  'cancelled'
);

-- Create windows table
CREATE TABLE public.windows (
  id SERIAL PRIMARY KEY,
  window_number INTEGER NOT NULL UNIQUE,
  is_active BOOLEAN DEFAULT true,
  current_queue_id UUID,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Insert 4 windows
INSERT INTO public.windows (window_number) VALUES (1), (2), (3), (4);

-- Create queue table
CREATE TABLE public.queue (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  queue_number INTEGER NOT NULL,
  transaction_type transaction_type NOT NULL,
  student_name TEXT NOT NULL,
  student_id TEXT,
  status queue_status DEFAULT 'waiting',
  window_id INTEGER REFERENCES public.windows(id),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  called_at TIMESTAMP WITH TIME ZONE,
  completed_at TIMESTAMP WITH TIME ZONE
);

-- Create feedback table
CREATE TABLE public.feedback (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  queue_id UUID REFERENCES public.queue(id) ON DELETE CASCADE,
  rating INTEGER CHECK (rating >= 1 AND rating <= 5),
  comment TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Create daily counter for queue numbers
CREATE TABLE public.queue_counter (
  id SERIAL PRIMARY KEY,
  date DATE DEFAULT CURRENT_DATE UNIQUE,
  last_number INTEGER DEFAULT 0
);

-- Function to get next queue number
CREATE OR REPLACE FUNCTION public.get_next_queue_number()
RETURNS INTEGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  next_num INTEGER;
BEGIN
  INSERT INTO queue_counter (date, last_number)
  VALUES (CURRENT_DATE, 1)
  ON CONFLICT (date) 
  DO UPDATE SET last_number = queue_counter.last_number + 1
  RETURNING last_number INTO next_num;
  
  RETURN next_num;
END;
$$;

-- Enable RLS
ALTER TABLE public.queue ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.feedback ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.windows ENABLE ROW LEVEL SECURITY;

-- Public read access for queue (anyone can see the queue)
CREATE POLICY "Anyone can view queue" ON public.queue FOR SELECT USING (true);
CREATE POLICY "Anyone can insert queue" ON public.queue FOR INSERT WITH CHECK (true);
CREATE POLICY "Anyone can update queue" ON public.queue FOR UPDATE USING (true);

-- Public access for windows
CREATE POLICY "Anyone can view windows" ON public.windows FOR SELECT USING (true);
CREATE POLICY "Anyone can update windows" ON public.windows FOR UPDATE USING (true);

-- Public access for feedback
CREATE POLICY "Anyone can insert feedback" ON public.feedback FOR INSERT WITH CHECK (true);
CREATE POLICY "Anyone can view feedback" ON public.feedback FOR SELECT USING (true);

-- Enable realtime for queue and windows
ALTER PUBLICATION supabase_realtime ADD TABLE public.queue;
ALTER PUBLICATION supabase_realtime ADD TABLE public.windows;