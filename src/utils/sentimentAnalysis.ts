import { pipeline, env } from '@huggingface/transformers';

// Configure transformers.js
env.allowLocalModels = false;

let sentimentPipeline: any = null;
let isLoading = false;

export interface SentimentResult {
  sentiment: 'positive' | 'negative' | 'neutral';
  score: number;
}

async function loadPipeline() {
  if (sentimentPipeline) return sentimentPipeline;
  if (isLoading) {
    // Wait for existing load to complete
    while (isLoading) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    return sentimentPipeline;
  }

  isLoading = true;
  try {
    console.log('Loading sentiment analysis model...');
    sentimentPipeline = await pipeline(
      'sentiment-analysis',
      'Xenova/distilbert-base-uncased-finetuned-sst-2-english',
      { device: 'webgpu' }
    );
    console.log('Sentiment model loaded successfully');
    return sentimentPipeline;
  } catch (error) {
    console.error('Failed to load sentiment model:', error);
    throw error;
  } finally {
    isLoading = false;
  }
}

export async function analyzeSentiment(text: string): Promise<SentimentResult> {
  if (!text || text.trim().length === 0) {
    return { sentiment: 'neutral', score: 0.5 };
  }

  try {
    const classifier = await loadPipeline();
    const result = await classifier(text);
    
    if (result && result.length > 0) {
      const { label, score } = result[0];
      const sentiment = label.toLowerCase() === 'positive' ? 'positive' : 'negative';
      
      return {
        sentiment,
        score: Number(score.toFixed(4))
      };
    }
    
    return { sentiment: 'neutral', score: 0.5 };
  } catch (error) {
    console.error('Sentiment analysis error:', error);
    return { sentiment: 'neutral', score: 0.5 };
  }
}

// Preload the model in background
export function preloadSentimentModel() {
  loadPipeline().catch(() => {
    // Silently fail on preload, will retry on actual use
  });
}
