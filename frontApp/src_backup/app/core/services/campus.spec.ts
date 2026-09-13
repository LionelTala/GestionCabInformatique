import { TestBed } from '@angular/core/testing';
import { Campus } from './campus';

describe('Campus', () => {
  let service: Campus;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Campus);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
