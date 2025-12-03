/**
 * Intl.Segmenter Shim for V8 13.5 Bug Workaround
 * 
 * V8 13.5 has a critical bug where calling Intl.Segmenter.prototype.segment()
 * causes a segmentation fault due to null pointer dereference in JSSegments::Create.
 * 
 * This shim overrides the native segment() method with a JavaScript implementation
 * that provides basic (locale-unaware) segmentation without crashing.
 * 
 * References:
 * - https://github.com/nodejs/node/issues/51752
 * - V8 bug in src/objects/js-segments.cc:33
 * 
 * NOTE: This is a fallback implementation that:
 * - Splits text by Unicode code points (grapheme clusters)
 * - Ignores locale-specific rules
 * - Ignores granularity option (word/sentence/grapheme all treated as grapheme)
 * - Provides iterator and containing() method for compatibility
 */
(function() {
    // Only patch if Intl.Segmenter exists
    if (typeof Intl === 'undefined' || typeof Intl.Segmenter === 'undefined') {
        return;
    }
    
    const OriginalSegmenter = Intl.Segmenter;
    
    // Save reference to original (buggy) method
    const originalSegment = OriginalSegmenter.prototype.segment;
    
    // Override with safe implementation
    OriginalSegmenter.prototype.segment = function(input) {
        const string = String(input);
        const segments = [];
        
        // Use Array.from to handle Unicode surrogate pairs properly
        const chars = Array.from(string);
        let byteIndex = 0;
        
        for (const char of chars) {
            segments.push({
                segment: char,
                index: byteIndex,
                input: string,
                isWordLike: /\w/.test(char)
            });
            // Count actual UTF-16 code units for index
            byteIndex += char.length;
        }
        
        // Return an object that mimics the native Segments interface
        const result = {
            // Iterator protocol
            [Symbol.iterator]: function() {
                let i = 0;
                return {
                    next: function() {
                        if (i < segments.length) {
                            return { value: segments[i++], done: false };
                        }
                        return { done: true };
                    }
                };
            },
            
            // containing() method: find segment at given position
            containing: function(position) {
                const n = Number(position);
                if (!Number.isFinite(n) || n < 0) {
                    return undefined;
                }
                
                for (const seg of segments) {
                    if (seg.index <= n && n < seg.index + seg.segment.length) {
                        return seg;
                    }
                }
                
                return undefined;
            }
        };
        
        return result;
    };
    
    // Mark as shimmed for debugging
    OriginalSegmenter.prototype.segment.__shimmed = true;
})();
